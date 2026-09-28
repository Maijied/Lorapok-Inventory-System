<?php

declare(strict_types=1);

namespace App\Domain\Impersonation;

use App\Domain\Central\AuditLog;
use App\Models\ImpersonationToken;
use App\Models\Tenant;
use App\Models\Tenant\User as TenantUser;
use App\Models\User;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Operators entering a shop as one of its staff.
 *
 * This is the most dangerous capability in the system — it is, by design, a
 * way for us to act inside somebody else's business. So it is built to be
 * awkward in exactly the places where being awkward is correct:
 *
 *   - A reason is required, and it is stored, not just prompted for.
 *   - Read-only unless read-write was explicitly asked for.
 *   - The token dies in a minute and works once.
 *   - Entering, and every write inside, is written to the audit log.
 *   - The shop is told: an unmissable banner, for the whole session.
 *
 * None of this stops a determined operator. It makes what they did legible
 * afterwards, which is the honest goal.
 */
final class ImpersonationService
{
    /** Minimum length of a reason that is actually a reason. */
    private const MIN_REASON = 10;

    /**
     * Mint a single-use token for one operator to enter one shop.
     *
     * @param  array<int, string>  $abilities
     */
    public function issue(
        Tenant $tenant,
        TenantUser $asUser,
        User $operator,
        string $reason,
        array $abilities = [ImpersonationToken::READ_ONLY],
        ?Request $request = null,
    ): ImpersonationToken {
        $reason = trim($reason);

        // "asked" and "." are not reasons. The value of this field is that
        // someone reading the log six months later understands what happened,
        // and a one-word entry defeats the whole mechanism.
        if (mb_strlen($reason) < self::MIN_REASON) {
            throw new DomainException(
                'Give a reason of at least '.self::MIN_REASON.' characters. '
                .'It is recorded and the shop can ask to see it.'
            );
        }

        if (! $operator->is_super_admin) {
            throw new DomainException('Only a super admin can enter a shop.');
        }

        $token = ImpersonationToken::create([
            'tenant_id' => $tenant->id,
            'tenant_user_id' => $asUser->getKey(),
            'super_admin_id' => $operator->id,
            'reason' => $reason,
            // Anything unrecognised is discarded rather than trusted: a typo
            // in an ability string must not silently become write access.
            'abilities' => array_values(array_intersect(
                $abilities,
                [ImpersonationToken::READ_ONLY, ImpersonationToken::READ_WRITE],
            )) ?: [ImpersonationToken::READ_ONLY],
            'request_ip' => $request?->ip() ?? request()->ip() ?? '0.0.0.0',
            'user_agent' => $request?->userAgent() ?? request()->userAgent(),
            'expires_at' => now()->addSeconds(ImpersonationToken::LIFETIME_SECONDS),
        ]);

        AuditLog::record(
            'impersonation.issued',
            $token,
            $operator,
            after: [
                'as_user_id' => $asUser->getKey(),
                'reason' => $reason,
                'abilities' => $token->abilities,
                'expires_at' => $token->expires_at->toIso8601String(),
            ],
            tenant: $tenant,
        );

        return $token;
    }

    /**
     * Spend a token and sign in as the shop user.
     *
     * Consumed before the session is established, so a replayed link fails
     * even if two requests arrive together.
     */
    public function consume(ImpersonationToken $token): TenantUser
    {
        if (! $token->isUsable()) {
            AuditLog::record(
                'impersonation.rejected',
                $token,
                null,
                after: [
                    'why' => $token->consumed_at !== null ? 'already used' : 'expired',
                ],
                tenant: $token->tenant,
            );

            throw new DomainException('That link has expired or has already been used.');
        }

        $token->forceFill(['consumed_at' => now()])->save();

        $user = TenantUser::query()->findOrFail($token->tenant_user_id);

        Auth::guard('tenant')->login($user);
        Auth::shouldUse('tenant');

        session()->put('impersonation', [
            'token' => $token->id,
            'operator_id' => $token->super_admin_id,
            'reason' => $token->reason,
            'can_write' => $token->canWrite(),
        ]);

        AuditLog::record(
            'impersonation.started',
            $token,
            $token->superAdmin,
            after: ['as_user_id' => $user->getKey(), 'can_write' => $token->canWrite()],
            tenant: $token->tenant,
        );

        return $user;
    }

    /** Whether the current session is an operator inside someone's shop. */
    public function active(): bool
    {
        return session()->has('impersonation');
    }

    /** Whether the current impersonation session may change anything. */
    public function canWrite(): bool
    {
        return (bool) session()->get('impersonation.can_write', false);
    }

    /** @return array<string, mixed>|null */
    public function context(): ?array
    {
        $context = session()->get('impersonation');

        return is_array($context) ? $context : null;
    }

    public function end(): void
    {
        $context = $this->context();

        Auth::guard('tenant')->logout();
        session()->forget('impersonation');

        if ($context !== null) {
            AuditLog::record('impersonation.ended', null, null, after: $context);
        }
    }
}
