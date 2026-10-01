<?php

namespace Database\Seeders;

use App\Support\Permissions\PermissionCatalog;

/**
 * The default tenant role set, defined once and applied to every demo company.
 *
 * These are starting points, not fixed rules: each company may create, rename
 * or delete its own roles. What matters is that authorisation always resolves
 * through permissions, so the labels here carry no authority of their own.
 */
final class TenantRoleDefinitions
{
    /**
     * @return array<string, array{name: string, description: string, permissions: array<int, string>}>
     */
    public static function for(): array
    {
        return [
            'owner' => [
                'name' => 'Owner',
                'description' => 'Full control over the company.',
                'permissions' => array_keys(PermissionCatalog::tenant()),
            ],
            'manager' => [
                'name' => 'Manager',
                'description' => 'Day-to-day management without deletions.',
                'permissions' => [
                    'products.view', 'products.create', 'products.update',
                    'inventory.view', 'inventory.adjust', 'inventory.transfer',
                    'sales.view', 'sales.create', 'sales.update',
                    'purchases.view', 'purchases.create', 'purchases.update',
                    'reports.sales', 'reports.profit',
                    'users.view', 'roles.view', 'settings.view',
                ],
            ],
            'sales' => [
                'name' => 'Sales',
                'description' => 'Front-counter and order handling.',
                'permissions' => [
                    'products.view', 'inventory.view',
                    'sales.view', 'sales.create', 'sales.update', 'sales.return',
                ],
            ],
            'warehouse' => [
                'name' => 'Warehouse',
                'description' => 'Stock control.',
                'permissions' => [
                    'products.view', 'inventory.view', 'inventory.adjust',
                    'inventory.transfer', 'purchases.view',
                ],
            ],
            'accountant' => [
                'name' => 'Accountant',
                'description' => 'Financial reporting.',
                'permissions' => [
                    'sales.view', 'purchases.view',
                    'reports.sales', 'reports.profit', 'settings.view',
                ],
            ],
        ];
    }
}
