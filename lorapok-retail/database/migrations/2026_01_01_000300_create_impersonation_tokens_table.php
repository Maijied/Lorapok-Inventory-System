<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonation_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            // The tenant-side user being impersonated. Not a FK: that user lives
            // in a different database.
            $table->unsignedBigInteger('tenant_user_id');
            $table->foreignId('super_admin_id')->constrained('users')->cascadeOnDelete();

            // A typed justification is mandatory before a token is issued.
            $table->text('reason');
            // Default ['read-only']; write access is not offered in v1.
            $table->json('abilities');

            // Bound to the requesting IP so a leaked link is not usable.
            $table->string('request_ip', 45);
            $table->text('user_agent')->nullable();

            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_tokens');
    }
};
