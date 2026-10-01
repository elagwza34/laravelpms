<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\MasterData\AttributeRequest;
use App\Http\Resources\AttributeResource;
use App\Models\Attribute;

/**
 * Attribute CRUD. Values may be created inline with the attribute; the
 * controller stamps company_id from the parent rather than accepting it.
 */
class AttributeController extends TenantCrudController
{
    protected function model(): string
    {
        return Attribute::class;
    }

    protected function resource(): string
    {
        return AttributeResource::class;
    }

    protected function storeRequest(): string
    {
        return AttributeRequest::class;
    }

    protected function updateRequest(): string
    {
        return AttributeRequest::class;
    }

    /**
     * @return array<int, string>
     */
    protected function listRelations(): array
    {
        return ['values'];
    }

    /**
     * @return array<int, string>
     */
    protected function detailRelations(): array
    {
        return ['values'];
    }
}
