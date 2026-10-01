<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MasterData\AttributeValueRequest;
use App\Http\Resources\AttributeValueResource;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Attribute values, always addressed through their parent attribute.
 *
 * This controller is deliberately NOT a TenantCrudController: every action here
 * takes the attribute as well, and proving that the value belongs to that
 * attribute is the whole point.
 *
 * Both lookups are tenant-scoped, so a value id belonging to another company is
 * simply not found and the request 404s.
 */
class AttributeValueController extends Controller
{
    public function index(string $company, int|string $attributeId): JsonResponse
    {
        $attribute = Attribute::query()->findOrFail($attributeId);

        $values = AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->orderBy('id')
            ->paginate(min((int) request()->input('per_page', 50), 100));

        return ApiResponse::paginated($values);
    }

    public function store(AttributeValueRequest $request, string $company, int|string $attributeId): JsonResponse
    {
        $attribute = Attribute::query()->findOrFail($attributeId);

        $value = AttributeValue::query()->create([
            'value' => $request->validated('value'),
            'status' => $request->validated('status', RecordStatus::Active->value),
            // Stamped from the parent so the composite FK always agrees.
            'company_id' => $attribute->company_id,
            'attribute_id' => $attribute->id,
        ]);

        return ApiResponse::success(
            AttributeValueResource::make($value->load('attribute')),
            201
        );
    }

    public function show(string $company, int|string $attributeId, int|string $valueId): JsonResponse
    {
        return ApiResponse::success(
            AttributeValueResource::make($this->scopedValue($attributeId, $valueId)->load('attribute'))
        );
    }

    public function update(AttributeValueRequest $request, string $company, int|string $attributeId, int|string $valueId): JsonResponse
    {
        $value = $this->scopedValue($attributeId, $valueId);

        $value->fill([
            'value' => $request->validated('value'),
            'status' => $request->validated('status', $value->status->value),
        ])->save();

        return ApiResponse::success(AttributeValueResource::make($value->load('attribute')));
    }

    public function destroy(string $company, int|string $attributeId, int|string $valueId): JsonResponse
    {
        $this->scopedValue($attributeId, $valueId)->delete();

        return ApiResponse::message('Attribute value deleted.');
    }

    /**
     * Resolve a value while proving it belongs to the given attribute.
     *
     * Both queries are tenant-scoped, so this is the second half of the IDOR
     * defence: the id must be in this tenant AND under this attribute.
     */
    private function scopedValue(int|string $attributeId, int|string $valueId): AttributeValue
    {
        $attribute = Attribute::query()->findOrFail($attributeId);

        return AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->findOrFail($valueId);
    }
}
