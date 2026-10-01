<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\MasterData\SupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;

/**
 * Supplier CRUD.
 *
 * Suppliers exist so a product can carry different purchasing terms for each of
 * them; there is deliberately no "default supplier" anywhere in this controller.
 */
class SupplierController extends TenantCrudController
{
    protected function model(): string
    {
        return Supplier::class;
    }

    protected function resource(): string
    {
        return SupplierResource::class;
    }

    protected function storeRequest(): string
    {
        return SupplierRequest::class;
    }

    protected function updateRequest(): string
    {
        return SupplierRequest::class;
    }
}
