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
         * Brands are tenant-owned master data. A Product references at most one,
         * so this is a nullable belongsTo on products (added later).
         *
         * Uniqueness is scoped per company: two companies may each have a brand
         * called "Apple" without colliding.
         */
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            // Storage path/reference only — never binary image data.
            $table->string('logo')->nullable();
            $table->text('description')->nullable();

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug'], 'brands_company_slug_unique');
            $table->index(['company_id', 'status'], 'brands_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
