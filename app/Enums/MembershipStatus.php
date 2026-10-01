<?php

namespace App\Enums;

/**
 * Status of the link between a user and a company.
 *
 * Invited users exist but cannot sign in to the tenant; Suspended users were
 * members before and have had their access revoked without deleting history.
 */
enum MembershipStatus: string
{
    case Active = 'active';
    case Invited = 'invited';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
