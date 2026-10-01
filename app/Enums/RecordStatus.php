<?php

namespace App\Enums;

/**
 * Active/inactive state shared by the tenant-owned master data introduced in
 * Phase 2 (brands, categories, attributes, units, suppliers, products).
 *
 * INACTIVE records stay in the database and remain visible in history, but
 * cannot be picked for new operations. This is distinct from deletion, which is
 * always a soft delete.
 */
enum RecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

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
