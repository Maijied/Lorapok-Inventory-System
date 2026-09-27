<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New shops start in light mode.
 *
 * A shop counter is bright and frequently beside a window. A dark till under
 * that glare is hard to read at the moment it matters most — with a customer
 * waiting. Dark stays right for the operator panel, where sessions are long
 * and the tables are dense, and it stays available to any shop that prefers
 * it.
 *
 * Only the DEFAULT changes. Existing shops keep whatever they are on: a theme
 * is a preference, and silently flipping one out from under a shop that has
 * been using it is not an upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('theme', 10)->default('light')->change();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('theme', 10)->default('dark')->change();
        });
    }
};
