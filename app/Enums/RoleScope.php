<?php

namespace App\Enums;

/**
 * Whether a role belongs to the SaaS platform itself or to a single tenant.
 *
 * The two scopes are deliberately kept on one table but are never mixed:
 * a tenant role can never grant platform permissions and vice versa.
 */
enum RoleScope: string
{
    case Platform = 'platform';
    case Tenant = 'tenant';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
