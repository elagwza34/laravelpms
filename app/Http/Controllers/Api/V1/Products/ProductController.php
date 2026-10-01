<?php

namespace App\Http\Controllers\Api\V1\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Products\StoreProductRequest;
use App\Http\Requests\Api\Products\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Services\Products\ProductWriter;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Product CRUD, scoped to the authenticated tenant.
 *
 * WHY PRODUCTS ARE RESOLVED HERE AND NOT BY ROUTE BINDING
 * -------------------------------------------------------
 * Laravel's SubstituteBindings middleware sits in the global API stack, which
 * always runs BEFORE route-level middleware. ResolveTenant is route-level, so
 * at binding time no tenant is active: CompanyScope applies no filter and a
 * foreign product id resolves successfully. That is precisely the IDOR this
 * module must not have.
 *
 * Resolving the product inside the action — after authentication, tenant
 * resolution, the subscription guard and the permission check — makes the
 * lookup order-independent. Every method goes through findProduct(), so there
 * is a single place where isolation is enforced.
 *
 * The {company} slug is never treated as authority: ResolveTenant has already
 * proven an active membership for it.
 */
class ProductController extends Controller
{
    /**
     * Per-page cap so a client cannot request the entire table in one call.
     */
    private const MAX_PER_PAGE = 100;

    public function __construct(protected ProductWriter $writer) {}

    /**
     * Resolve a product within the ACTIVE TENANT.
     *
     * Product::query() carries CompanyScope, so a product owned by another
     * company is not found and findOrFail() produces a 404 — identical to a
     * genuinely missing id, so ids cannot be probed.
     */
    private function findProduct(int|string $id): Product
    {
        return Product::query()->findOrFail($id);
    }

    /**
     * Paginated, searchable product list.
     *
     * Filters are all optional and all tenant-scoped; the global scope applies
     * to the whereHas() sub-queries too, so filtering by a foreign category id
     * simply matches nothing.
     */
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->with(Product::listRelations())
            ->search($request->string('search')->toString() ?: null)
            ->ofType($request->string('product_type')->toString() ?: null)
            ->ofBrand($request->integer('brand_id') ?: null)
            ->inCategory($request->integer('category_id') ?: null)
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->string('status')->toString())
            )
            // Default ordering keeps pagination stable between pages.
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 25), self::MAX_PER_PAGE))
            ->withQueryString();

        return ApiResponse::paginated($products);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->writer->create(
            attributes: $request->safe()->except([
                'units', 'category_ids', 'supplier_terms',
                'attribute_value_ids', 'variants',
            ]),
            units: $request->validated('units'),
            categoryIds: $request->validated('category_ids', []),
            supplierTerms: $request->validated('supplier_terms', []),
            variants: $request->validated('variants'),
            attributeValueIds: $request->validated('attribute_value_ids', []),
        );

        return ApiResponse::success(new ProductResource($product), 201);
    }

    public function show(string $company, int|string $product): JsonResponse
    {
        return ApiResponse::success(
            new ProductResource($this->findProduct($product)->load(Product::detailRelations()))
        );
    }

    public function update(UpdateProductRequest $request, string $company, int|string $productId): JsonResponse
    {
        $product = $this->findProduct($productId);

        $updated = $this->writer->update(
            product: $product,
            attributes: $request->safe()->except([
                'units', 'category_ids', 'supplier_terms',
                'attribute_value_ids', 'variants',
            ]),
            units: $request->has('units') ? $request->validated('units') : null,
            categoryIds: $request->has('category_ids') ? $request->validated('category_ids') : null,
            supplierTerms: $request->has('supplier_terms') ? $request->validated('supplier_terms') : null,
            variants: $request->has('variants') ? $request->validated('variants') : null,
            attributeValueIds: $request->has('attribute_value_ids')
                ? $request->validated('attribute_value_ids')
                : null,
        );

        return ApiResponse::success(new ProductResource($updated));
    }

    /**
     * Archive a product.
     *
     * Soft delete only: sales, purchases and stock movements that reference it
     * in later phases must stay referentially intact.
     */
    public function destroy(string $company, int|string $productId): JsonResponse
    {
        $this->findProduct($productId)->delete();

        return ApiResponse::message('Product archived.');
    }

    /**
     * Deactivate without deleting, so the record stays visible in history but
     * can no longer be chosen for new operations.
     */
    public function deactivate(string $company, int|string $productId): JsonResponse
    {
        $product = $this->findProduct($productId);
        $product->markInactive();

        return ApiResponse::success(new ProductResource($product->load(Product::listRelations())));
    }

    public function activate(string $company, int|string $productId): JsonResponse
    {
        $product = $this->findProduct($productId);
        $product->markActive();

        return ApiResponse::success(new ProductResource($product->load(Product::listRelations())));
    }
}
