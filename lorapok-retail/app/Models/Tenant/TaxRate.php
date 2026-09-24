<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A tax rate, held as integer basis points (1850 = 18.50%).
 *
 * Percentages stored as floats are how tax totals drift away from invoice
 * totals by a paisa and stop reconciling.
 */
#[Fillable(['name', 'rate_bps', 'is_inclusive', 'is_active'])]
class TaxRate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'rate_bps' => 'integer',
            'is_inclusive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Human-readable percentage, e.g. "18.5%". */
    public function percentage(): string
    {
        return rtrim(rtrim(number_format($this->rate_bps / 100, 2), '0'), '.').'%';
    }
}
