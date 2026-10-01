<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\MembershipStatus;
use App\Enums\RoleScope;
use App\Enums\UserStatus;
use App\Support\Tenancy\TenancyContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

/**
 * A single login identity, shared by both scopes of the SaaS.
 *
 * Platform staff and tenant employees use the same table. A user is a member of
 * the platform when `is_platform_user` is true, and belongs to any number of
 * companies through the company_memberships join — never through a company_id
 * column, which could only ever express one company.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_platform_user',
        'status',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_user' => 'boolean',
            'status' => UserStatus::class,
        ];
    }

    /**
     * Every company link for this user.
     *
     * @return HasMany<CompanyMembership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class);
    }

    /**
     * Only the memberships that currently grant access.
     *
     * @return HasMany<CompanyMembership, $this>
     */
    public function activeMemberships(): HasMany
    {
        return $this->hasMany(CompanyMembership::class)
            ->where('status', MembershipStatus::Active);
    }

    /**
     * Platform roles held by this user. Platform scope only.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function platformRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'permission_role_user', 'user_id', 'role_id')
            ->where('roles.scope', RoleScope::Platform->value)
            ->whereNull('roles.company_id');
    }

    public function isPlatformUser(): bool
    {
        return (bool) $this->is_platform_user;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * The membership that grants access to the currently active tenant.
     */
    public function currentMembership(): ?CompanyMembership
    {
        $tenantId = app(TenancyContext::class)->id();

        if ($tenantId === null) {
            return null;
        }

        return $this->memberships()
            ->where('company_id', $tenantId)
            ->where('status', MembershipStatus::Active)
            ->with('role.permissions')
            ->first();
    }

    /**
     * Whether the user holds a membership in the given company.
     */
    public function belongsToCompany(int $companyId): bool
    {
        return $this->memberships()
            ->where('company_id', $companyId)
            ->where('status', MembershipStatus::Active)
            ->exists();
    }

    /**
     * Effective permissions for the current request.
     *
     * Inside a tenant context the membership's role decides; at platform level
     * the platform roles decide. The two namespaces never mix, so a tenant role
     * can never grant a platform capability and vice versa.
     *
     * @return Collection<int, string>
     */
    public function effectivePermissions(): Collection
    {
        $membership = $this->currentMembership();

        if ($membership !== null) {
            return collect($membership->permissionNames());
        }

        if ($this->isPlatformUser()) {
            return $this->platformRoles()
                ->with('permissions')
                ->get()
                ->flatMap(fn (Role $role): array => $role->permissionNames())
                ->unique()
                ->values();
        }

        return collect();
    }

    /**
     * Whether the user may perform an action in the current context.
     */
    public function hasPermission(string $permission): bool
    {
        // A super admin short-circuit, decided by an explicit permission rather
        // than by a role name.
        if ($this->effectivePermissions()->contains('*')) {
            return true;
        }

        return $this->effectivePermissions()->contains($permission);
    }

    /**
     * @return array<int, string>
     */
    public function permissionNames(): array
    {
        return $this->effectivePermissions()->all();
    }
}
