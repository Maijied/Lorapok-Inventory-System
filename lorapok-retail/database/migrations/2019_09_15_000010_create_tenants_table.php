<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTenantsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();

            // Shop identity. These are real columns rather than keys inside the
            // `data` json because the super admin lists, searches and sorts on
            // them, and because branding is read on every tenant request.
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();

            // Constrained to an approved palette, not a free colour picker, so
            // contrast stays compliant. Interpolated into CSS, so it is also
            // validated server-side before it ever reaches a template.
            $table->string('accent', 7)->default('#7c5cff');
            $table->string('theme', 10)->default('dark');

            $table->string('status', 20)->default('trialing');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();

            $table->timestamps();
            $table->json('data')->nullable();

            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
}
