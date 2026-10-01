<?php

namespace App\Enums;

/**
 * How a product's tax is expressed.
 *
 * This is product-level configuration only. It is deliberately NOT a taxation
 * engine: no country rules, no tax rates registry, no rounding policy.
 *
 * A product with no tax leaves BOTH tax_type and tax_value null, rather than
 * inventing a third "none" value that carries no information.
 */
enum TaxType: string
{
    /** A percentage of the selling price, e.g. 14 for 14%. */
    case Percentage = 'percentage';

    /** A fixed amount per unit, e.g. 20 EGP. */
    case Fixed = 'fixed';

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
