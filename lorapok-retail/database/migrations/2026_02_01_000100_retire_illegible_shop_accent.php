<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Move shops off the retired accent.
 *
 * `#7c5cff` was the default for every shop provisioned before this, and no
 * text is legible on it: 4.35:1 with white, 4.11:1 with near-black, against an
 * AA floor of 4.5:1. It sat in the gap where neither choice works, so the
 * primary button on every screen of those shops had an unreadable label.
 *
 * `#9075ff` is the nearest violet that clears AA in both roles the accent
 * plays — as a button fill (5.25:1) and as coloured text on the dark surface
 * (5.71:1). ContrastTest holds the palette to both from here on.
 *
 * Central table, so a data change belongs in the migration rather than a
 * queued job: there is exactly one `tenants` table, not one per shop.
 */
return new class extends Migration
{
    private const RETIRED = '#7c5cff';

    private const REPLACEMENT = '#9075ff';

    public function up(): void
    {
        DB::table('tenants')
            ->where('accent', self::RETIRED)
            ->update(['accent' => self::REPLACEMENT]);

        // The column DEFAULT matters as much as the existing rows: without
        // this, the very next shop provisioned without an explicit accent gets
        // the retired colour straight back.
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('accent', 7)->default(self::REPLACEMENT)->change();
        });
    }

    public function down(): void
    {
        // Reversible for a clean rollback, though it restores a colour that
        // fails AA — which is why it was retired.
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('accent', 7)->default(self::RETIRED)->change();
        });

        DB::table('tenants')
            ->where('accent', self::REPLACEMENT)
            ->update(['accent' => self::RETIRED]);
    }
};
