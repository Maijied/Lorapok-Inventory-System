<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Enums\ProductType;
use App\Models\Tenant\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'sku' => strtoupper(Str::random(8)),
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.Str::random(4),
            'description' => fake()->optional()->sentence(),
            'type' => ProductType::Standard,
            'track_stock' => true,
            'reorder_level' => 0,
            'is_active' => true,
        ];
    }

    public function serialized(): static
    {
        return $this->state(fn () => ['type' => ProductType::Serialized]);
    }

    public function service(): static
    {
        return $this->state(fn () => [
            'type' => ProductType::Service,
            'track_stock' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
