<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cash drawer accountability.
     *
     * A shift opens with a counted float, takes money during the day, and
     * closes with a physical count. The difference between what the system
     * expected and what was actually in the drawer is the thing a shop owner
     * most wants to see, and it cannot be computed unless both numbers are
     * recorded.
     *
     * The old system had no concept of a till at all.
     */
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('register_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable();

            $table->bigInteger('opening_float_minor')->default(0);
            // What the system believes should be in the drawer: float plus
            // cash taken, less cash paid out.
            $table->bigInteger('expected_cash_minor')->nullable();
            // What was actually counted at close.
            $table->bigInteger('counted_cash_minor')->nullable();
            // counted - expected. Negative is a shortfall.
            $table->bigInteger('variance_minor')->nullable();

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['cash_register_id', 'closed_at']);
        });

        Schema::create('register_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('register_session_id')->constrained()->cascadeOnDelete();
            // 'in' for a cash sale or a paid-in, 'out' for a refund or payout.
            $table->string('direction', 5);
            $table->string('type', 20);
            $table->bigInteger('amount_minor');
            $table->nullableMorphs('reference');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index('register_session_id');
        });

        // A sale belongs to the shift that rang it up, so a day's takings can
        // be reconciled against a specific cashier's drawer.
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('register_session_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['register_session_id']);
            $table->dropColumn('register_session_id');
        });

        Schema::dropIfExists('register_movements');
        Schema::dropIfExists('register_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
