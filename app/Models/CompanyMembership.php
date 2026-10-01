<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Database\Factories\CompanyMembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Links a user to a company with a specific role.
 *
 * This model is the authority for "may this user act inside this company?".
 * Tenant resolution consults it — never the URL, never a request parameter.
 *
 * @property int $id
 * @property int $user_id
 * @property int $company_id
 * @property int $role_id
 * @property MembershipStatus $status
 * @property Carbon|null $joined_at
 */
class CompanyMembership extends Model
{
    /** @use HasFactory<CompanyMembershipFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'company_id',
        'role_id',
        'status',
        'joined_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === MembershipStatus::Active;
    }

    /**
     * Permission names this membership grants, via its role.
     *
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return $this->relationLoaded('role')
            ? $this->role->permissionNames()
            : $this->role()->with('permissions')->first()?->permissionNames() ?? [];
    }
}
