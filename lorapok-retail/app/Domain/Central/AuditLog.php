<?php

declare(strict_types=1);

namespace App\Domain\Central;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A record of what an operator did to a shop.
 *
 * Written to the central database on the central connection explicitly,
 * because these entries must survive even when the action concerned a
 * tenant whose own database is later dropped.
 */
class AuditLog
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public static function record(
        string $action,
        Tenant|Model|null $subject = null,
        ?User $actor = null,
        ?array $before = null,
        ?array $after = null,
    ): void {
        DB::connection(config('tenancy.database.central_connection'))
            ->table('central_audit_logs')
            ->insert([
                'actor_id' => $actor?->id,
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'tenant_id' => $subject instanceof Tenant ? $subject->id : null,
                'before' => $before !== null ? json_encode($before) : null,
                'after' => $after !== null ? json_encode($after) : null,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);
    }
}
