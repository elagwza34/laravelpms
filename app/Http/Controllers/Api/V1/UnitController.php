<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\MasterData\UnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;

/**
 * Unit CRUD. Tenant-owned master data; conversion rates live on product_units,
 * not here, because a rate belongs to one product rather than to the unit.
 */
class UnitController extends TenantCrudController
{
    protected function model(): string
    {
        return Unit::class;
    }

    protected function resource(): string
    {
        return UnitResource::class;
    }

    protected function storeRequest(): string
    {
        return UnitRequest::class;
    }

    protected function updateRequest(): string
    {
        return UnitRequest::class;
    }
}
