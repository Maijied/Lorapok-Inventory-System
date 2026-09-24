<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give stock_levels a surrogate primary key.
     *
     * It was created with a composite primary key of (location_id,
     * product_variant_id). That is correct as a constraint, but Eloquent has
     * no real support for composite keys: modelling it required
     * `$primaryKey = null`, which emits "Using null as an array offset is
     * deprecated" on every save.
     *
     * The pair keeps its uniqueness as a unique index, so the guarantee is
     * unchanged — one level row per variant per location.
     *
     * The foreign keys have to be dropped first: MySQL refuses to drop the
     * PRIMARY index while a foreign key depends on it
     * ("Cannot drop index 'PRIMARY': needed in a foreign key constraint").
     */
    public function up(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['product_variant_id']);
        });

        DB::statement('ALTER TABLE stock_levels DROP PRIMARY KEY');

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->id()->first();
            $table->unique(['location_id', 'product_variant_id'], 'stock_levels_location_variant_unique');
        });

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stock_levels', function (Blueprint $table) {
            $table->dropForeign(['location_id']);
            $table->dropForeign(['product_variant_id']);
            $table->dropUnique('stock_levels_location_variant_unique');
            $table->dropColumn('id');
        });

        DB::statement('ALTER TABLE stock_levels ADD PRIMARY KEY (location_id, product_variant_id)');

        Schema::table('stock_levels', function (Blueprint $table) {
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('product_variant_id')->references('id')->on('product_variants')->cascadeOnDelete();
        });
    }
};
