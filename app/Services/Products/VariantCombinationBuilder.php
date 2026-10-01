<?php

namespace App\Services\Products;

use App\Enums\RecordStatus;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;

/**
 * Builds variant combinations for a variable product.
 *
 * Variants carry NO commercial data. A variant is only a set of chosen
 * attribute values, so this class is responsible for exactly two things:
 * deriving a canonical key, and proving the chosen values are legitimate for
 * this company.
 *
 * Cross-company safety comes from two independent layers:
 *
 *  1. Every AttributeValue is resolved through the tenant-scoped model, so a
 *     value belonging to another company simply does not exist here.
 *  2. The database carries a composite foreign key (product_id, company_id),
 *     so even a hand-written query cannot attach a variant to a foreign product.
 */
class VariantCombinationBuilder
{
    /**
     * Canonical key for a set of attribute value ids.
     *
     * Sorting makes the key order-independent: [3, 7] and [7, 3] describe the
     * same combination and must collide rather than create two variants.
     *
     * @param  array<int, int>  $valueIds
     */
    public function keyFor(array $valueIds): string
    {
        $ids = array_values(array_unique(array_map('intval', $valueIds)));
        sort($ids);

        return implode('-', $ids);
    }

    /**
     * Resolve the given ids to attribute values owned by the active tenant.
     *
     * @param  array<int, int>  $valueIds
     * @return Collection<int, AttributeValue>
     *
     * @throws \RuntimeException when an id does not exist in this tenant.
     */
    public function resolveValues(array $valueIds): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $valueIds)));

        $values = AttributeValue::query()
            ->whereIn('id', $ids)
            ->get();

        $found = $values->pluck('id')->all();

        // Any missing id is either nonexistent or owned by another company.
        // Both are refused identically so the API reveals nothing.
        $missing = array_diff($ids, $found);

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'One or more attribute values do not belong to this company.'
            );
        }

        return $values;
    }

    /**
     * Every combination of the supplied values, grouped by attribute.
     *
     * Given Color[Black, White] and Size[S, M] this yields the four real
     * option combinations. Only offered values are crossed, so an attribute
     * the product does not use contributes nothing.
     *
     * @param  Collection<int, AttributeValue>  $values
     * @return array<int, array<int, int>> list of attribute_value id sets
     */
    public function combinationsFor(Collection $values): array
    {
        $byAttribute = $values->groupBy('attribute_id');

        $combination = [];

        foreach ($byAttribute as $group) {
            $combination[] = $group->pluck('id')->all();
        }

        if ($combination === []) {
            return [];
        }

        // Cartesian product of the per-attribute value lists.
        $result = [[]];

        foreach ($combination as $ids) {
            $next = [];

            foreach ($result as $partial) {
                foreach ($ids as $id) {
                    $next[] = array_merge($partial, [$id]);
                }
            }

            $result = $next;
        }

        return $result;
    }

    /**
     * Create the variants for a variable product.
     *
     * Runs inside the caller's transaction so a failure part-way leaves no
     * orphaned variants behind.
     *
     * @param  array<int, array<int, int>>  $combinations  sets of attribute_value ids
     * @return Collection<int, ProductVariant>
     */
    public function createVariants(Product $product, array $combinations): Collection
    {
        return collect($combinations)->map(function (array $valueIds) use ($product): ProductVariant {
            $key = $this->keyFor($valueIds);

            /*
             * firstOrCreate keeps the operation idempotent and lets the unique
             * index (product_id, combination_key) be the real guard against a
             * race between two concurrent writes.
             */
            $variant = ProductVariant::query()->firstOrCreate(
                [
                    'product_id' => $product->id,
                    'combination_key' => $key,
                ],
                [
                    // Stamped from the product; never accepted from the client.
                    'company_id' => $product->company_id,
                    // Null-safe: a product created without an explicit status
                    // falls back to the column default rather than exploding.
                    'status' => ($product->status ?? RecordStatus::Active)->value,
                ]
            );

            $variant->attributeValues()->sync($valueIds);

            return $variant;
        });
    }
}
