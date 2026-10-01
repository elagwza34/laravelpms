<?php

namespace App\Http\Requests\Api\Products;

use App\Models\Product;

class StoreProductRequest extends ProductRequest
{
    protected function productBeingUpdated(): ?Product
    {
        return null;
    }
}
