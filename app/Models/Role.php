<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'slug', 'description', 'menu_group', 'is_super_admin'])]
class Role extends Model
{
    use HasFactory;

    public const DOSEN_PA = 'dosen_pa';

    public const DOSEN_PEMBIMBING = 'dosen_pembimbing';

    public const DOSEN_PENGUJI = 'dosen_penguji';

    public const DOSEN_ROLES = [self::DOSEN_PA, self::DOSEN_PEMBIMBING, self::DOSEN_PENGUJI];

    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Replace this role's permissions, leaving an audit entry when they
     * change — pivot writes don't fire the events AuditObserver listens to.
     *
     * @param  iterable<int, int|string>  $permissionIds
     */
    public function syncPermissionsWithAudit(iterable $permissionIds): void
    {
        $before = $this->permissions()->pluck('name')->sort()->values()->all();

        $this->permissions()->sync($permissionIds);
        $this->unsetRelation('permissions');

        $after = $this->permissions()->pluck('name')->sort()->values()->all();

        if ($before !== $after) {
            AuditLog::record('Mengubah izin', $this, ['permissions' => $before], ['permissions' => $after]);
        }
    }

    /**
     * A super-admin role always passes — this is the one deliberate bypass
     * in the whole RBAC system, so the access-control screens themselves
     * can never be locked out by a misconfigured permission set.
     */
    public function hasPermission(string $name): bool
    {
        return $this->is_super_admin || $this->permissions->contains('name', $name);
    }
}
