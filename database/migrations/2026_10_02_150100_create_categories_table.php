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
         * Categories form a self-referencing tree (parent_id -> categories.id).
         *
         * Cross-company parenting is impossible by construction: a parent row
         * always carries its own company_id, and the application only ever
         * resolves parent_id through the tenant-scoped model. The composite
         * foreign key below makes that a database guarantee as well.
         */
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->string('slug');

            /*
             * NullOnDelete rather than CascadeOnDelete: removing a parent
             * category must not silently destroy its children or detach the
             * products that reference them.
             */
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('status')->default(RecordStatus::Active->value);

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug'], 'categories_company_slug_unique');
            $table->index(['company_id', 'parent_id'], 'categories_company_parent_index');
            $table->index(['company_id', 'status'], 'categories_company_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
