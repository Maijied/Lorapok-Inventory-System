<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where the public site puts what it collects.
 *
 * Both tables are central: a lead does not belong to a shop, and an
 * application is by definition from someone who does not have one yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message');

            // Kept for abuse handling and for answering "where did this come
            // from" — not for tracking anyone across the site.
            $table->string('ip', 45)->nullable();
            $table->string('referrer')->nullable();

            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('handled_at');
        });

        Schema::create('shop_applications', function (Blueprint $table): void {
            $table->id();

            // What the shop wants to be called and to live at. The slug is
            // checked against Tenant::RESERVED_SLUGS on submit so an applicant
            // learns immediately, rather than after an operator tries to
            // provision it.
            $table->string('shop_name');
            $table->string('slug')->index();

            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('owner_phone')->nullable();
            $table->string('city')->nullable();

            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();

            // Set once the application becomes a real shop, so the two are
            // linked and an application cannot be converted twice.
            $table->uuid('tenant_id')->nullable()->index();

            $table->string('ip', 45)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_applications');
        Schema::dropIfExists('contact_messages');
    }
};
