<?php

use App\Enums\MembershipStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The join between a user and a company. It is deliberately a separate
         * table instead of user_id / company_id columns on `users`, because a
         * user may belong to several companies and must hold a different role in
         * each one. A 1:1 column pair could never express that.
         */
        Schema::create('company_memberships', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->foreignId('role_id')
                ->constrained('roles')
                ->cascadeOnDelete();

            $table->string('status')->default(MembershipStatus::Active->value);

            /*
             * Null while the invitation is pending. Once accepted it records when
             * the user actually joined the company.
             */
            $table->timestamp('joined_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
             * Hard guarantee against duplicate membership for the same pair,
             * which is a database constraint rather than an application check
             * that a race condition could bypass.
             */
            $table->unique(['user_id', 'company_id'], 'company_memberships_user_company_unique');

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_memberships');
    }
};
