<?php

use App\Enums\RoleScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            /*
             * Human readable key. Role names are display labels only —
             * authorisation always resolves through permissions.
             */
            $table->string('slug');

            $table->string('scope')->default(RoleScope::Tenant->value);

            /*
             * NULL for platform roles, set for tenant roles. A single table keeps
             * the permission engine simple while the scope column guarantees the
             * two namespaces can never be mixed.
             */
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->cascadeOnDelete();

            $table->text('description')->nullable();

            /*
             * System roles ship with the application and cannot be deleted.
             * Custom roles created by a tenant owner remain deletable.
             */
            $table->boolean('is_system')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // A slug may repeat across different companies, but not twice per company.
            $table->unique(['company_id', 'scope', 'slug'], 'roles_company_scope_slug_unique');
            $table->index('scope');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
