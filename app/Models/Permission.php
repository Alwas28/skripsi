<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A permission is code-defined (see database/seeders/RolePermissionSeeder.php)
 * — new ones get registered there as features are built. What admins manage
 * at runtime is which roles hold which permissions (Role::permissions()).
 */
#[Fillable(['name', 'action', 'action_label', 'feature', 'feature_label'])]
class Permission extends Model
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}
