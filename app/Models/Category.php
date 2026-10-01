<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasRecordStatus;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A tenant-owned product category, organised as a self-referencing tree.
 *
 * A product may belong to many categories (the pivot lives on product_category).
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property RecordStatus $status
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use BelongsToCompany, HasFactory, HasRecordStatus, SoftDeletes;

    /**
     * @var list<string>
     *
     * company_id is intentionally absent — it is stamped from the tenant
     * context, never accepted from the client.
     */
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if ($category->isDirty('name') && blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category');
    }

    /**
     * Ancestors from the immediate parent upwards.
     *
     * Iterative rather than recursive so a deep or accidentally cyclic tree can
     * never blow the stack.
     *
     * @return Collection<int, Category>
     */
    public function ancestors(): Collection
    {
        $chain = collect();
        $current = $this->parent;
        $guard = 0;

        while ($current !== null && $guard < 50) {
            $chain->push($current);
            $current = $current->parent;
            $guard++;
        }

        return $chain;
    }

    /**
     * This category plus every descendant, used to delete a whole subtree.
     *
     * @return HasMany<Category, $this>
     */
    public function descendants(): HasMany
    {
        return $this->children();
    }

    /**
     * Whether the given category id sits anywhere below this one.
     */
    public function isAncestorOf(int $categoryId): bool
    {
        return $this->ancestors()->contains('id', $categoryId)
            || $this->descendants()->where('id', $categoryId)->exists();
    }
}
