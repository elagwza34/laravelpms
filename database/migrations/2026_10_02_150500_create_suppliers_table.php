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
         * Suppliers are tenant-owned and are the source of purchasing cost.
         *
         * The same product may cost a different amount from each supplier, and
         * that difference is modelled on product_suppliers rather than as a
         * single cost column on products.
         */
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug'], 'suppliers_company_slug_unique');
            $table->index(['company_id', 'status'], 'suppliers_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
