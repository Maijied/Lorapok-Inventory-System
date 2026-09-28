<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A request for a new shop, from someone who does not have one yet.
 *
 * Converted into a real tenant by an operator, which is what `tenant_id`
 * records — it also makes converting the same application twice impossible to
 * do by accident.
 *
 * @property int $id
 * @property string $shop_name
 * @property string $slug
 * @property string $owner_name
 * @property string $owner_email
 * @property string|null $owner_phone
 * @property string|null $city
 * @property string $status
 * @property string|null $tenant_id
 */
class ShopApplication extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    protected $guarded = [];

    /** Central, always — see ShopVerification for why this is pinned. */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection');
    }

    /** @var array<string, string> */
    protected $attributes = ['status' => self::PENDING];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function isConverted(): bool
    {
        return $this->tenant_id !== null;
    }
}
