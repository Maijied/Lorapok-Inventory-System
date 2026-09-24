<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Staff of one shop.
 *
 * Deliberately a different class from App\Models\User, which is a Lorapok
 * super admin. They live in different databases and authenticate through
 * different guards, so a shop account can never be used on the central app
 * and vice versa.
 */
#[Fillable(['name', 'email', 'password', 'phone', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    /** Spatie resolves roles through this guard, not the default one. */
    protected string $guard_name = 'tenant';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * A suspended account keeps its history but cannot sign in.
     * Staff are deactivated, not deleted, so their sales stay attributable.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }
}
