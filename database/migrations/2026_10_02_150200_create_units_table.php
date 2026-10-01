<?php

use App\Enums\RecordStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Units are reusable company-level master data (Piece, Box, Kg ...).
         * A conversion rate is deliberately NOT stored here: it belongs to the
         * product/unit pairing, because "1 Carton = 12 Pieces" is a statement
         * about one product, not about the Carton unit in general.
         */
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('abbreviation')->nullable();

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name'], 'units_company_name_unique');
            $table->index(['company_id', 'status'], 'units_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
