<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'precision'])]
class Unit extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['precision' => 'integer'];
    }
}
