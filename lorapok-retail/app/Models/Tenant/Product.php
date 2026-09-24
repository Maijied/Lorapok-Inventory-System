<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use League\CommonMark\CommonMarkConverter;

/**
 * Property types after casting, which PHPStan cannot infer from the schema
 * alone — the `type` column is a string in the database but a ProductType
 * enum on the model.
 *
 * @property ProductType $type
 * @property bool $track_stock
 * @property bool $is_active
 * @property ?int $warranty_days
 * @property ?string $description
 */
#[Fillable([
    'sku', 'name', 'slug', 'description', 'category_id', 'brand_id',
    'unit_id', 'tax_rate_id', 'type', 'track_stock', 'reorder_level',
    'warranty_days', 'is_active',
])]
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'reorder_level' => 'decimal:4',
            'warranty_days' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Description is stored as Markdown and rendered with raw HTML disabled.
     *
     * The system this replaces stored raw WYSIWYG HTML and echoed it with
     * {!! !!}, which was a stored-XSS hole: any staff member could inject a
     * script that ran for everyone else. Markdown-in / escaped-HTML-out closes
     * that by construction rather than by remembering to escape at each call
     * site.
     */
    public function renderedDescription(): HtmlString
    {
        if (blank($this->description)) {
            return new HtmlString('');
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        return new HtmlString((string) $converter->convert($this->description));
    }

    /** Serialised products must capture an IMEI or serial for every unit sold. */
    public function isSerialized(): bool
    {
        return $this->type === ProductType::Serialized;
    }

    /**
     * Server-side search. The old system loaded every row into the browser and
     * filtered client-side, which stops working at a few thousand products.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "{$term}%")
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', "{$term}%"))
                ->orWhereHas('variants.barcodes', fn (Builder $b) => $b->where('barcode', $term));
        });
    }
}
