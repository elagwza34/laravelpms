<?php

namespace App\Http\Requests\Api\MasterData;

use App\Enums\RecordStatus;
use App\Http\Requests\Api\ApiFormRequest;
use App\Models\Category;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Category validation, including the nested-category rules.
 *
 * A parent must belong to the SAME company (tenantExists, not a bare exists),
 * and a category may not become its own ancestor — which would create a cycle
 * that makes the tree impossible to walk.
 */
class CategoryRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $categoryId = $category instanceof Category ? $category->id : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('categories', 'slug')
                    ->where('company_id', $this->activeCompanyId())
                    ->ignore($categoryId),
            ],

            // Tenant-scoped: a parent from another company is not "exists".
            'parent_id' => ['nullable', 'integer', $this->tenantExists(Category::class)],

            'status' => ['nullable', Rule::in(RecordStatus::values())],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($this->filled('company_id')) {
                $v->errors()->add('company_id', 'company_id is determined by the authenticated tenant and cannot be set.');
            }

            $this->validateNoCycle($v);
        });
    }

    /**
     * Reject a parent choice that would put the category inside its own subtree.
     */
    private function validateNoCycle(Validator $v): void
    {
        $parentId = $this->input('parent_id');

        if ($parentId === null) {
            return;
        }

        $category = $this->route('category');

        // A category can never be its own parent.
        if ($category instanceof Category && (int) $parentId === $category->id) {
            $v->errors()->add('parent_id', 'A category cannot be its own parent.');

            return;
        }

        if (! $category instanceof Category) {
            return;
        }

        // Nor may it become a descendant of itself.
        $descendantIds = $this->descendantIdsOf($category->id);

        if (in_array((int) $parentId, $descendantIds, true)) {
            $v->errors()->add('parent_id', 'A category cannot be moved under one of its own children.');
        }
    }

    /**
     * Depth-first collection of descendant ids, guarded against cycles.
     *
     * @return array<int, int>
     */
    private function descendantIdsOf(int $rootId): array
    {
        $found = [];
        $queue = [$rootId];

        while ($queue !== []) {
            $current = array_shift($queue);

            // Scoped query: only this tenant's categories are ever traversed.
            $children = Category::query()
                ->where('parent_id', $current)
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
                if (in_array($childId, $found, true) || $childId === $rootId) {
                    continue;
                }

                $found[] = $childId;
                $queue[] = $childId;
            }
        }

        return $found;
    }
}
