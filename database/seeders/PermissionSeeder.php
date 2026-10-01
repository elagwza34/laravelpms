<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\Permissions\PermissionCatalog;
use Illuminate\Database\Seeder;

/**
 * Synchronises the code-defined permission catalogue into the database.
 *
 * Permissions are never accepted from a request payload; they are seeded from
 * PermissionCatalog so the set of possible actions stays finite and auditable.
 * Running this seeder again is safe and idempotent.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PermissionCatalog::all() as $name => $description) {
            Permission::query()->updateOrCreate(
                ['name' => $name],
                [
                    'group' => PermissionCatalog::groupFor($name),
                    'description' => $description,
                ]
            );
        }
    }
}
