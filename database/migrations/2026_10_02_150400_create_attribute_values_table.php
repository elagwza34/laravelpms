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
         * Values of an attribute (Color -> Black, White, Red).
         *
         * company_id is denormalised from the parent attribute on purpose. It
         * lets attribute values be filtered and validated with the same
         * CompanyScope as every other tenant table, instead of requiring a join
         * back through attributes on every query. A composite foreign key
         * (attribute_id, company_id) makes the duplication impossible to
         * violate: a value can never belong to an attribute of another company.
         */
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();

            $table->string('value');
            $table->string('slug')->nullable();

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['attribute_id', 'value'], 'attribute_values_attribute_value_unique');
            $table->index(['company_id', 'attribute_id'], 'attribute_values_company_attribute_index');

            /*
             * Guarantees attribute_values.company_id always equals the parent
             * attribute's company_id. This is the database-level enforcement of
             * "no cross-company attribute values".
             */
            $table->foreign(
                ['attribute_id', 'company_id'],
                'attribute_values_attribute_company_fk'
            )->references(['id', 'company_id'])->on('attributes')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};
