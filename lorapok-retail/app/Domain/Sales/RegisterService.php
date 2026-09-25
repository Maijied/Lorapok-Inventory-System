<?php

declare(strict_types=1);

namespace App\Domain\Sales;

use App\Enums\PaymentMethodType;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\Payment;
use App\Models\Tenant\RegisterSession;
use App\Models\Tenant\Sale;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The cash drawer.
 *
 * Only cash is tracked here. A card or bKash payment never enters the
 * drawer, so counting it at close would manufacture a phantom shortfall.
 */
class RegisterService
{
    /** Open a shift with a counted float. */
    public function open(CashRegister $register, User $user, int $openingFloatMinor = 0): RegisterSession
    {
        if ($openingFloatMinor < 0) {
            throw new InvalidSale('An opening float cannot be negative.');
        }

        return DB::transaction(function () use ($register, $user, $openingFloatMinor) {
            // One open shift per till. Two cashiers sharing a drawer makes any
            // variance unattributable.
            $existing = RegisterSession::query()
                ->where('cash_register_id', $register->id)
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new InvalidSale("{$register->name} already has an open shift.");
            }

            return RegisterSession::create([
                'cash_register_id' => $register->id,
                'opened_by' => $user->id,
                'opening_float_minor' => $openingFloatMinor,
                'opened_at' => now(),
            ]);
        });
    }

    /**
     * Record cash taken for a sale.
     *
     * Called after a payment is recorded; non-cash methods are ignored.
     */
    public function recordSalePayment(RegisterSession $session, Sale $sale, Payment $payment): void
    {
        if (! $this->isCash($payment)) {
            return;
        }

        $this->move($session, 'in', 'sale', $payment->amount_minor, $sale, $payment->created_by);
    }

    /** Cash handed back for a refund. */
    public function recordRefund(
        RegisterSession $session,
        int $amountMinor,
        ?Model $reference = null,
        ?int $actorId = null,
    ): void {
        $this->move($session, 'out', 'refund', $amountMinor, $reference, $actorId);
    }

    /** Money put into the drawer for a reason other than a sale. */
    public function payIn(RegisterSession $session, int $amountMinor, string $reason, ?int $actorId = null): void
    {
        $this->assertOpen($session);
        $this->move($session, 'in', 'pay_in', $amountMinor, null, $actorId, $reason);
    }

    /** Money taken out — a supplier paid in cash, or a drop to the safe. */
    public function payOut(RegisterSession $session, int $amountMinor, string $reason, ?int $actorId = null): void
    {
        $this->assertOpen($session);

        if ($amountMinor > $session->expectedCashMinor()) {
            throw new InvalidSale('Cannot pay out more than the drawer holds.');
        }

        $this->move($session, 'out', 'pay_out', $amountMinor, null, $actorId, $reason);
    }

    /**
     * Close the shift against a physical count.
     *
     * The variance is stored rather than hidden: a shortfall is exactly what
     * a shop owner needs to see.
     */
    public function close(
        RegisterSession $session,
        User $user,
        int $countedCashMinor,
        ?string $note = null,
    ): RegisterSession {
        $this->assertOpen($session);

        if ($countedCashMinor < 0) {
            throw new InvalidSale('A counted amount cannot be negative.');
        }

        return DB::transaction(function () use ($session, $user, $countedCashMinor, $note) {
            $expected = $session->expectedCashMinor();

            $session->forceFill([
                'closed_by' => $user->id,
                'closed_at' => now(),
                'expected_cash_minor' => $expected,
                'counted_cash_minor' => $countedCashMinor,
                // Negative means less cash than there should be.
                'variance_minor' => $countedCashMinor - $expected,
                'note' => $note,
            ])->save();

            return $session->refresh();
        });
    }

    private function isCash(Payment $payment): bool
    {
        return $payment->method?->type === PaymentMethodType::Cash;
    }

    private function assertOpen(RegisterSession $session): void
    {
        if (! $session->isOpen()) {
            throw new InvalidSale('That shift is already closed.');
        }
    }

    private function move(
        RegisterSession $session,
        string $direction,
        string $type,
        int $amountMinor,
        ?Model $reference = null,
        ?int $actorId = null,
        ?string $reason = null,
    ): void {
        if ($amountMinor <= 0) {
            throw new InvalidSale('A cash movement must be greater than zero.');
        }

        $session->movements()->create([
            'direction' => $direction,
            'type' => $type,
            'amount_minor' => $amountMinor,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'created_by' => $actorId,
            'reason' => $reason,
        ]);
    }
}
