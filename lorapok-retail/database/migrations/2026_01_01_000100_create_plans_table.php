<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Money is always an integer in minor units with an explicit
            // currency. Never a float, never a decimal handled in PHP.
            $table->bigInteger('price_minor')->default(0);
            $table->char('currency', 3)->default('BDT');
            $table->string('interval', 20)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(14);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            // e.g. max_users, max_products, max_locations, max_monthly_sales.
            // null value = unlimited.
            $table->string('key', 50);
            $table->unsignedInteger('value')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
        Schema::dropIfExists('plans');
    }
};
