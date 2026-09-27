<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One line of prose per plan, for the public pricing page.
 *
 * The table was designed before there was anywhere to show it. A card headed
 * only "Shop" and a price tells a shop owner nothing about whether it is the
 * one they want, and the alternative — describing plans in the Blade template
 * — puts copy in a deploy that pricing was deliberately kept out of.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->string('description', 255)->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
