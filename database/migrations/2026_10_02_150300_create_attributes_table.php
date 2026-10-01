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
         * Attributes are COMPANY-LEVEL ONLY. There are deliberately no global
         * attributes: Company A owns Color/Size/Fabric while Company B owns
         * Storage/RAM/Capacity, and neither can reference the other.
         */
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug'], 'attributes_company_slug_unique');
            $table->index(['company_id', 'status'], 'attributes_company_status_index');

            /*
             * Referenced by attribute_values' composite foreign key, which is
             * what makes a value impossible to attach to another company's
             * attribute at the database level.
             */
            $table->unique(['id', 'company_id'], 'attributes_id_company_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};
