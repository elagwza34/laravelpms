<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Roles never own permissions: permissions are global definitions that
         * many roles across many companies point at. A company can therefore only
         * grant permissions that already exist, which keeps the permission set
         * auditable and prevents tenant A from inventing a permission that
         * tenant B would then be checked against.
         */
        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();

            $table->foreignId('permission_id')
                ->constrained('permissions')
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->timestamps();

            // Prevents granting the same permission to the same role twice.
            $table->unique(['permission_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_role');
    }
};
