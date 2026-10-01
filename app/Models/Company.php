<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A tenant: one customer company on the SaaS platform.
 *
 * This model is deliberately NOT tenant-scoped — it *is* the tenant. It is the
 * anchor for every other tenant-owned record.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CompanyStatus $status
 */
class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
        ];
    }

    /**
     * All users linked to this company, optionally narrowed by membership state.
     *
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * Roles that belong to this company. Platform roles are not included.
     *
     * @return HasMany<Role, $this>
     */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    /**
     * Derive a URL-safe slug from a company name.
     */
    public static function slugify(string $name): string
    {
        $slug = Str::slug($name);

        return $slug !== '' ? $slug : 'company';
    }
}
