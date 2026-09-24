<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-shop settings.
     *
     * In the system this replaces, the shop's name, address, phone numbers,
     * warranty terms, currency and invoice footer were hard-coded directly
     * into the invoice Blade template. Changing any of them meant editing
     * source, and running a second shop was impossible. Everything that
     * identifies a shop now lives here.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // JSON so a setting can hold a scalar, a list or a structure
            // without needing a schema change per setting.
            $table->json('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
