<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Role Model
 *
 * Represents a local role in the system (e.g., admin, viewer).
 * SSO-authenticated users are mapped to one of these roles
 * for local authorization decisions.
 *
 * @property int    $id
 * @property string $name
 * @property string $display_name
 * @property string $description
 */
class Role extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'display_name',
        'description',
    ];

    /**
     * Get all users assigned to this role.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
