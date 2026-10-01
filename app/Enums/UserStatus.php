<?php

namespace App\Enums;

/**
 * Account state of a single user account.
 *
 * A user account is shared between the platform and any number of companies,
 * so a single status column governs the login itself. Tenant-specific access
 * is governed separately by CompanyMembership::status.
 */
enum UserStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
