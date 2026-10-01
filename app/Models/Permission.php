<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A single atomic capability, e.g. "products.create".
 *
 * Permissions are global definitions. They are never created by a tenant and
 * never carry a company_id: a tenant can only grant permissions that already
 * exist. This is what makes the authorisation rules auditable — the complete
 * list of possible actions is finite, defined by the application.
 *
 * @property int $id
 * @property string $name
 * @property string $group
 * @property string|null $description
 */
class Permission extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'group',
        'description',
    ];

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * The resource half of the dot-notation key, e.g. "products".
     */
    public function group(): string
    {
        return str($this->name)->beforeLast('.')->toString();
    }

    /**
     * The action half of the dot-notation key, e.g. "create".
     */
    public function action(): string
    {
        return str($this->name)->afterLast('.')->toString();
    }
}
