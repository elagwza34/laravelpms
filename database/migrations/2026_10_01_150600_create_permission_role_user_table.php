<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Grants a platform role to a user.
         *
         * Platform staff are not members of a company, so they cannot use
         * company_memberships (which requires a company_id and represents tenant
         * employment). This join carries platform assignments only, and is
         * constrained in code so that only platform-scoped roles can appear here.
         */
        Schema::create('permission_role_user', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->timestamps();

            // A user holds a given platform role only once.
            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role_user');
    }
};
