<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mark which central users run Lorapok Retail itself.
     *
     * The central `users` table is a different population from a shop's
     * staff: these are Lorapok operators, not cashiers. Being a super admin
     * is a deliberate flag rather than "anyone with an account", because the
     * central app can provision, suspend and impersonate shops.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super_admin')->default(false)->after('email');
            $table->boolean('is_active')->default(true)->after('is_super_admin');
            $table->timestamp('last_login_at')->nullable();

            $table->index('is_super_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn(['is_super_admin', 'is_active', 'last_login_at']);
        });
    }
};
