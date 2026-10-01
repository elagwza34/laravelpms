<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default development seed.
 *
 * Order matters: permissions must exist before roles can reference them, and
 * roles must exist before users can be attached to them.
 *
 * DemoUserSeeder contains demo accounts with a known password and must never be
 * run against production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            MasterDataSeeder::class,
            DemoUserSeeder::class,
        ]);
    }
}
