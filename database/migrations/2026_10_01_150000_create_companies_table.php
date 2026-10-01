<?php

use App\Enums\CompanyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            /*
             * The slug powers the /client1 style URL. It is a lookup key only and
             * is NEVER treated as a security boundary: authorisation is decided
             * from the authenticated user's membership, not from this value.
             */
            $table->string('slug')->unique();

            $table->string('status')->default(CompanyStatus::Trial->value);

            // Soft deletes keep tenant data intact when a company is removed.
            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
