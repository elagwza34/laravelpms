<?php

namespace App\Models;

use App\Enums\RoleScope;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A named bundle of permissions, scoped either to the platform or to one tenant.
 *
 * Role names (Owner, Manager, Sales...) are presentation only. Authorisation
 * always resolves through the attached permissions, so renaming a role or
 * creating a custom one never changes what a role can actually do.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property RoleScope $scope
 * @property int|null $company_id
 * @property bool $is_system
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'scope',
        'company_id',
        'description',
        'is_system',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => RoleScope::class,
            'is_system' => 'boolean',
        ];
    }

    /**
     * The owning tenant. Always null for platform roles.
     *
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    /**
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    public function isPlatformRole(): bool
    {
        return $this->scope === RoleScope::Platform;
    }

    public function isTenantRole(): bool
    {
        return $this->scope === RoleScope::Tenant;
    }

    /**
     * Every permission name granted by this role.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return $this->permissions->pluck('name')->all();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->permissions->contains('name', $permission);
    }

    /**
     * Attach permissions, replacing the previous set.
     *
     * Accepts permission ids OR permission names, because both appear in real code
     * (seeds use names, the API uses ids). Mixing them in one call is not supported
     * and would be ambiguous.
     *
     * @param  array<int, int|string>  $permissions
     */
    public function syncPermissions(array $permissions): void
    {
        if ($permissions === []) {
            $this->permissions()->detach();

            return;
        }

        $isNameList = is_string(reset($permissions));

        $ids = $isNameList
            ? Permission::query()->whereIn('name', $permissions)->pluck('id')
            : collect($permissions);

        $this->permissions()->sync($ids->all());
    }
}
