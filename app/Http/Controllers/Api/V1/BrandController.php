<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\MasterData\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;

/**
 * Brand CRUD. Tenant scoping comes from CompanyScope via BelongsToCompany.
 */
class BrandController extends TenantCrudController
{
    protected function model(): string
    {
        return Brand::class;
    }

    protected function resource(): string
    {
        return BrandResource::class;
    }

    protected function storeRequest(): string
    {
        return BrandRequest::class;
    }

    protected function updateRequest(): string
    {
        return BrandRequest::class;
    }
}
