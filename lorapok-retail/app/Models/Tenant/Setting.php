<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * A shop's own settings: name, address, currency, invoice footer, and so on.
 *
 * Replaces the hard-coded shop identity that used to live in the invoice
 * template.
 */
#[Fillable(['key', 'value', 'group'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'array'];
    }

    /** Cache key is tenant-scoped automatically: stancl namespaces the cache per tenant. */
    private const CACHE_KEY = 'tenant.settings';

    /**
     * @return array<string, mixed>
     */
    public static function allSettings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->pluck('value', 'key')
            ->all());
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allSettings()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);

        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        // Any write path invalidates the cache, including mass updates made
        // outside put().
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
