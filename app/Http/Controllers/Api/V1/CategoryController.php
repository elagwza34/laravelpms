<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\MasterData\CategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

/**
 * Category CRUD, including the nested parent/child tree.
 *
 * The parent id is validated against the active tenant, so a category can never
 * be re-parented under another company's category.
 */
class CategoryController extends TenantCrudController
{
    protected function model(): string
    {
        return Category::class;
    }

    protected function resource(): string
    {
        return CategoryResource::class;
    }

    protected function storeRequest(): string
    {
        return CategoryRequest::class;
    }

    protected function updateRequest(): string
    {
        return CategoryRequest::class;
    }

    /**
     * @return array<int, string>
     */
    protected function detailRelations(): array
    {
        return ['parent'];
    }
}
