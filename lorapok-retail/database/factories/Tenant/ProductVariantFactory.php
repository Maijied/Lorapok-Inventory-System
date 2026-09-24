<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ProductVariant> */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $cost = fake()->numberBetween(50_00, 900_00);

        return [
            'product_id' => Product::factory(),
            'sku' => strtoupper(Str::random(10)),
            'name' => null,
            'cost_minor' => $cost,
            // Sells above cost so margin reports have something real to show.
            'price_minor' => (int) round($cost * fake()->randomFloat(2, 1.1, 1.6)),
            'is_active' => true,
        ];
    }
}
