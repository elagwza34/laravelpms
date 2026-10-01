<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();

            /*
             * Dot-notation permission key, e.g. "products.create". This is the
             * single source of truth for authorisation; role names are never
             * checked by application code.
             */
            $table->string('name')->unique();

            // Resource group used to render the role editor UI, e.g. "products".
            $table->string('group');

            $table->string('description')->nullable();

            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permissions');
    }
};
