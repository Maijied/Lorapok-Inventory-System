<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Someone writing in from the public site.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $message
 */
class ContactMessage extends Model
{
    protected $guarded = [];

    /** Central, always — see ShopVerification for why this is pinned. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }
}
