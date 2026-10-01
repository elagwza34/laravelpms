<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecordStatus;
use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Category;
use App\Support\Api\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Shared CRUD for the tenant-owned master data introduced in Phase 2
 * (brands, categories, units, attributes, suppliers).
 *
 * WHY A BASE CLASS
 * ----------------
 * These resources behave identically: scoped list, create, show, update, soft
 * delete. Writing five near-identical controllers would be five places for a
 * tenancy mistake to hide. Subclasses supply only the model, the resource and
 * the FormRequest classes — none of the query logic.
 *
 * TENANT SAFETY is inherited from CompanyScope, which every one of these models
 * applies. No method here reads company_id from the request.
 */
abstract class TenantCrudController extends Controller
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * @return class-string
     */
    abstract protected function resource(): string;

    /**
     * @return class-string
     */
    abstract protected function storeRequest(): string;

    /**
     * @return class-string
     */
    abstract protected function updateRequest(): string;

    /**
     * @return array<int, string>
     */
    protected function listRelations(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    protected function detailRelations(): array
    {
        return [];
    }

    /**
     * Base query — already tenant-scoped by CompanyScope.
     *
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return ($this->model())::query();
    }

    /**
     * @return class-string
     */
    protected function resourceClass(): string
    {
        return $this->resource();
    }

    public function index(Request $request, string $company): JsonResponse
    {
        $records = $this->query()
            ->with($this->listRelations())
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.addcslashes((string) $request->string('search'), '%_\\').'%';
                $query->where('name', 'like', $term);
            })
            ->when($request->filled('status'), function (Builder $query) use ($request): void {
                $query->where('status', $request->string('status')->toString());
            })
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 25), 100))
            ->withQueryString();

        return ApiResponse::paginated($records);
    }

    public function store(Request $request, string $company): JsonResponse
    {
        $validated = $this->validatedFor($this->storeRequest());

        /*
         * status is defaulted explicitly rather than left to the column default:
         * after insert() the model does not re-read the row, so the attribute
         * would stay null and the API would report a record that is not the one
         * actually stored.
         */
        $record = ($this->model())::query()->create(
            $this->attributesFrom($validated) + ['status' => RecordStatus::Active->value]
        );

        $this->applyRelations($record, $validated);

        return ApiResponse::success(
            $this->resourceClass()::make($record->load($this->detailRelations())),
            201
        );
    }

    public function show(string $company, int|string $id): JsonResponse
    {
        $record = $this->query()->with($this->detailRelations())->findOrFail($id);

        return ApiResponse::success($this->resourceClass()::make($record));
    }

    public function update(Request $request, string $company, int|string $id): JsonResponse
    {
        $record = $this->query()->findOrFail($id);

        $validated = $this->validatedFor($this->updateRequest());

        $record->fill($this->attributesFrom($validated))->save();
        $this->applyRelations($record, $validated);

        return ApiResponse::success(
            $this->resourceClass()::make($record->load($this->detailRelations()))
        );
    }

    /**
     * Resolve and run the declared FormRequest.
     *
     * The FormRequest is pulled out of the container rather than rebuilt from
     * the Request: Laravel's FormRequest already knows how to hydrate itself
     * from the current route and query, and Request::createFrom() does not.
     *
     * @param  class-string  $formRequest
     * @return array<string, mixed>
     */
    private function validatedFor(string $formRequest): array
    {
        /** @var FormRequest $instance */
        $instance = app($formRequest);

        return $instance->validated();
    }

    /**
     * Soft delete, so historical references stay intact.
     */
    public function destroy(string $company, int|string $id): JsonResponse
    {
        $this->query()->findOrFail($id)->delete();

        return ApiResponse::message('Record deleted.');
    }

    /**
     * Split validated input into model columns and relationship keys.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function attributesFrom(array $validated): array
    {
        return collect($validated)->except(['values', 'attribute_id'])->all();
    }

    /**
     * Persist nested relationship input.
     *
     * @param  array<string, mixed>  $validated
     */
    protected function applyRelations(Model $record, array $validated): void
    {
        if ($record instanceof Category && array_key_exists('parent_id', $validated)) {
            $record->parent_id = $validated['parent_id'];
            $record->save();
        }

        if ($record instanceof Attribute && array_key_exists('values', $validated)) {
            $this->syncAttributeValues($record, (array) $validated['values']);
        }
    }

    /**
     * Replace an attribute's values.
     *
     * The foreign key is set explicitly rather than relying on createMany():
     * the relationship infers attribute_id from the parent, but being explicit
     * keeps the composite foreign key (attribute_id, company_id) satisfiable
     * and readable.
     *
     * @param  array<int, string>  $values
     */
    private function syncAttributeValues(Attribute $attribute, array $values): void
    {
        $attribute->values()->delete();

        $attribute->values()->createMany(
            collect($values)
                ->filter()
                ->unique()
                ->map(fn (string $value): array => [
                    'attribute_id' => $attribute->getKey(),
                    // Stamped from the attribute so the composite FK always agrees.
                    'company_id' => $attribute->company_id,
                    'value' => $value,
                    'status' => RecordStatus::Active->value,
                ])->values()->all()
        );
    }
}
