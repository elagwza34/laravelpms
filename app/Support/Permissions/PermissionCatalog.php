<?php

namespace App\Support\Permissions;

/**
 * The single, authoritative catalogue of permissions.
 *
 * Permissions are defined in code and synced into the database, never created
 * from a request payload. That is deliberate:
 *
 *  - The set of possible actions stays finite and reviewable.
 *  - A tenant can only GRANT permissions that already exist, so one company can
 *    never invent a capability that another company is later checked against.
 *  - Adding a permission is a code review event, not a runtime surprise.
 *
 * Naming: "<resource>.<action>". Actions are kept coarse and reusable.
 */
final class PermissionCatalog
{
    /**
     * Wildcard granted only to platform super administrators.
     */
    public const ALL = '*';

    /**
     * Platform-level capabilities (the SaaS operator).
     *
     * @var array<string, string>
     */
    private const PLATFORM = [
        'companies.view' => 'View all companies',
        'companies.create' => 'Create a new company',
        'companies.update' => 'Update company details',
        'companies.delete' => 'Delete a company',
        'companies.suspend' => 'Suspend or reactivate a company',
        'platform_users.view' => 'View platform users',
        'platform_users.manage' => 'Create, edit and deactivate platform users',
        'roles.manage' => 'Manage roles and their permissions',
        'subscriptions.view' => 'View subscriptions and payments',
        'subscriptions.manage' => 'Approve, renew and cancel subscriptions',
        'audit.view' => 'View audit logs',
    ];

    /**
     * Tenant-level capabilities (a customer's own employees).
     *
     * These names are reserved up front so every company starts from the same
     * vocabulary, while remaining free to define additional custom roles.
     *
     * @var array<string, string>
     */
    private const TENANT = [
        'products.view' => 'View products',
        'products.create' => 'Create products',
        'products.update' => 'Update products',
        'products.delete' => 'Delete products',

        'categories.view' => 'View categories',
        'categories.create' => 'Create categories',
        'categories.update' => 'Update categories',
        'categories.delete' => 'Delete categories',

        'brands.view' => 'View brands',
        'brands.create' => 'Create brands',
        'brands.update' => 'Update brands',
        'brands.delete' => 'Delete brands',

        'attributes.view' => 'View attributes',
        'attributes.create' => 'Create attributes',
        'attributes.update' => 'Update attributes',
        'attributes.delete' => 'Delete attributes',

        'units.view' => 'View units',
        'units.create' => 'Create units',
        'units.update' => 'Update units',
        'units.delete' => 'Delete units',

        'suppliers.view' => 'View suppliers',
        'suppliers.create' => 'Create suppliers',
        'suppliers.update' => 'Update suppliers',
        'suppliers.delete' => 'Delete suppliers',

        'inventory.view' => 'View inventory',
        'inventory.adjust' => 'Adjust stock levels',
        'inventory.transfer' => 'Transfer stock between warehouses',

        'sales.view' => 'View sales',
        'sales.create' => 'Create a sale',
        'sales.update' => 'Update a sale',
        'sales.return' => 'Process a sales return',

        'purchases.view' => 'View purchases',
        'purchases.create' => 'Create a purchase',
        'purchases.update' => 'Update a purchase',
        'purchases.return' => 'Process a purchase return',

        'reports.sales' => 'View sales reports',
        'reports.profit' => 'View profit reports',

        'users.view' => 'View company users',
        'users.manage' => 'Manage company users',

        'roles.view' => 'View company roles',
        'roles.manage' => 'Manage company roles',

        'settings.view' => 'View company settings',
        'settings.manage' => 'Manage company settings',
    ];

    /**
     * Every platform permission, including the super-admin wildcard.
     *
     * @return array<string, string>
     */
    public static function platform(): array
    {
        return [self::ALL => 'Full unrestricted access'] + self::PLATFORM;
    }

    /**
     * Every tenant permission.
     *
     * @return array<string, string>
     */
    public static function tenant(): array
    {
        return self::TENANT;
    }

    /**
     * The union used when syncing the permissions table.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return self::platform() + self::tenant();
    }

    /**
     * The resource half of a permission key, e.g. "products" from
     * "products.create". Used to group permissions in the role editor.
     */
    public static function groupFor(string $permission): string
    {
        return $permission === self::ALL
            ? 'platform'
            : str($permission)->beforeLast('.')->toString();
    }
}
