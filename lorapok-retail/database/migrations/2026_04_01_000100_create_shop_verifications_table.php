<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Know who a shop actually is.
 *
 * Central, because verification is about the business we contract with rather
 * than anything happening inside their till.
 *
 * Almost every column here is personal data about a real person, and the
 * schema treats it that way:
 *
 *   - Identifiers are stored encrypted (TEXT, because ciphertext is longer
 *     than the value and not searchable — which is the point).
 *   - Documents are paths on a private disk, never public URLs.
 *   - A rejection carries its reason, because "rejected" with no explanation
 *     is what makes people reapply with the same mistake.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_verifications', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');

            // The business as it appears on the trade licence, which is often
            // not the name above the shop door.
            $table->string('legal_name')->nullable();
            $table->string('trade_licence_no')->nullable();

            /*
             * Encrypted at rest. TEXT rather than STRING because ciphertext is
             * substantially longer than the value, and deliberately not
             * indexed: an index on an encrypted column buys nothing, and a
             * plaintext one would defeat the encryption.
             */
            $table->text('bin_tin')->nullable();
            $table->text('owner_identifier')->nullable();

            $table->string('owner_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();

            // Paths on the `kyc` disk. Never a URL — a URL in a database is a
            // URL that ends up in a log.
            $table->string('licence_document_path')->nullable();
            $table->string('identifier_front_path')->nullable();
            $table->string('identifier_back_path')->nullable();
            $table->string('owner_photo_path')->nullable();

            $table->string('status', 20)->default('unverified');
            $table->text('rejection_reason')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            // One verification per shop: a second row would make "is this shop
            // verified" a question with two answers.
            $table->unique('tenant_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_verifications');
    }
};
