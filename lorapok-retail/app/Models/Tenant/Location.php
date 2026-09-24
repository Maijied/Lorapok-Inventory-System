<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['name', 'code', 'type', 'address', 'is_default', 'is_active'])]
class Location extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'address' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public static function default(): ?self
    {
        return static::where('is_default', true)->first() ?? static::first();
    }
}
