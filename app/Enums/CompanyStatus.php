<?php

namespace App\Enums;

/**
 * Lifecycle of a tenant (company) account.
 *
 * The values are stored as plain strings rather than a MySQL ENUM so that new
 * states can be introduced with a migration that does not rewrite the column.
 * They also map 1:1 onto the subscription lifecycle described in the business
 * model: a tenant can be trialing, actively subscribed, expired (data kept,
 * PMS blocked) or suspended by the platform owner.
 */
enum CompanyStatus: string
{
    case Trial = 'trial';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Trial => 'Trial',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Suspended => 'Suspended',
        };
    }

    /**
     * Whether employees may use the Product Management System.
     *
     * An expired tenant keeps every record and can still reach the customer
     * portal, but the PMS stays locked until the subscription is renewed.
     */
    public function allowsPmsAccess(): bool
    {
        return in_array($this, [self::Trial, self::Active], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
