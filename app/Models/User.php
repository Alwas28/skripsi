<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

#[Fillable(['name', 'email', 'password', 'study_program_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Keeps the forced-change flag true exactly while a student's password
     * is still their NIM, whichever screen changed the password.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->exists && $user->isDirty('password')) {
                $nim = $user->studentRecord()->value('nim');
                $user->must_change_password = $nim !== null && Hash::check($nim, $user->password);
            }
        });
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * The study program a prodi-staff account (Admin Prodi, Kaprodi) manages.
     */
    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /**
     * Replace this user's roles, leaving an audit entry when they change —
     * pivot writes don't fire the model events AuditObserver listens to.
     *
     * @param  iterable<int, int|string>  $roleIds
     */
    public function syncRolesWithAudit(iterable $roleIds): void
    {
        $before = $this->roles()->pluck('slug')->sort()->values()->all();

        $this->roles()->sync($roleIds);
        $this->unsetRelation('roles');

        $after = $this->roles()->pluck('slug')->sort()->values()->all();

        if ($before !== $after) {
            AuditLog::record('Mengubah role', $this, ['roles' => $before], ['roles' => $after]);
        }
    }

    public function whatsappNumber(): ?string
    {
        return $this->studentRecord?->no_wa ?: $this->lecturerRecord?->no_wa;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles->contains('is_super_admin', true);
    }

    /**
     * The sidebar menu groups (config/menu.php) this user's roles map to,
     * prodi first so staff duties lead a combined menu.
     *
     * @return list<string>
     */
    public function menuGroups(): array
    {
        return array_values(array_intersect(['prodi', 'dosen', 'mahasiswa'], $this->roles->pluck('menu_group')->all()));
    }

    /**
     * "aksi.fitur" check, e.g. hasPermission('tambah.user'). See
     * database/seeders/RolePermissionSeeder.php for the permission catalog
     * and App\Http\Middleware\EnsurePermission for route-level enforcement.
     */
    public function hasPermission(string $name): bool
    {
        return $this->roles->contains(fn (Role $role) => $role->hasPermission($name));
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function studentRecord(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function lecturerRecord(): HasOne
    {
        return $this->hasOne(Lecturer::class);
    }
}
