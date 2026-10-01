<?php

namespace App\Enums;

/**
 * The two product types supported by the PMS.
 *
 * SERVICE, BUNDLE and COMPOSITE are deliberately absent: they are not part of
 * the product model and no architecture is built for them.
 */
enum ProductType: string
{
    /**
     * A single stock-keeping unit with its own SKU, barcode and pricing.
     */
    case Simple = 'simple';

    /**
     * One inventory item offered in several option combinations (e.g. a
     * T-Shirt in Black/S and White/L).
     *
     * A variable product is still ONE inventory item: the variant rows only
     * describe which attribute values were chosen, and carry no price, stock,
     * SKU or barcode of their own.
     */
    case Variable = 'variable';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Whether this type must declare at least one variant combination.
     */
    public function requiresVariants(): bool
    {
        return $this === self::Variable;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
