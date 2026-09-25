<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Cash entering or leaving the drawer.
 *
 * @property int $amount_minor
 */
#[Fillable([
    'register_session_id', 'direction', 'type', 'amount_minor',
    'reference_type', 'reference_id', 'created_by', 'reason',
])]
class RegisterMovement extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['amount_minor' => 'integer'];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(RegisterSession::class, 'register_session_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
