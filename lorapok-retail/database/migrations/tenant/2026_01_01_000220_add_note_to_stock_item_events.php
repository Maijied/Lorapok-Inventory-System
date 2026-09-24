<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record why a tracked unit changed status.
     *
     * "Written off" on its own is not useful six months later; "water damage,
     * customer declined repair" is. The status history is the only place that
     * reason can live.
     */
    public function up(): void
    {
        Schema::table('stock_item_events', function (Blueprint $table) {
            $table->text('note')->nullable()->after('actor_id');
        });
    }

    public function down(): void
    {
        Schema::table('stock_item_events', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
