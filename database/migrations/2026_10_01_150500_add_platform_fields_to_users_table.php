<?php

use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            /*
             * Single account table for both scopes. `is_platform_user` marks staff
             * of the SaaS operator; tenant access is carried by
             * company_memberships instead of a company_id column, so one person
             * can be a platform admin and a member of several customers at once.
             */
            $table->boolean('is_platform_user')->default(false)->after('password');
            $table->string('status')->default(UserStatus::Active->value)->after('is_platform_user');

            // No timestamps() call here: the users table already has them.
            $table->index(['is_platform_user', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_platform_user', 'status']);
            $table->dropColumn(['is_platform_user', 'status']);
        });
    }
};
