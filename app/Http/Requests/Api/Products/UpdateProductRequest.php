<?php

namespace App\Http\Requests\Api\Products;

use App\Models\Product;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Validation for updating a product.
 *
 * Identical rules to creation except that every scalar is optional, so a PATCH
 * that only renames a product does not have to resend its units.
 */
class UpdateProductRequest extends ProductRequest
{
    /**
     * Refuse a foreign product id before any rule runs.
     *
     * Validation executes before the controller, so a cross-tenant id would
     * otherwise produce a 422 full of "units is required" noise that both wastes
     * work and hints that the resource exists somewhere. Resolving it here —
     * after ResolveTenant, so CompanyScope applies — makes the response an
     * honest 404, identical to a genuinely missing id.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $id = $this->route('product');

        if ($id !== null && Product::query()->find($id) === null) {
            throw new NotFoundHttpException('Product not found.');
        }
    }

    /**
     * The product being updated, resolved within the active tenant.
     *
     * Resolved here rather than through route-model binding because binding
     * runs before ResolveTenant, so the tenant scope would not be applied yet.
     */
    protected function productBeingUpdated(): ?Product
    {
        $id = $this->route('product');

        return $id === null ? null : Product::query()->find($id);
    }
}
