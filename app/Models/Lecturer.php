<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'study_program_id', 'nidn', 'name', 'gelar_depan', 'gelar_belakang', 'expertise_field', 'no_wa', 'is_active'])]
class Lecturer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class);
    }

    /**
     * Lecturers whose login account holds the given role.
     */
    #[Scope]
    protected function holdingRole(Builder $query, string $roleSlug): void
    {
        $query->whereHas('user.roles', fn (Builder $roles) => $roles->where('slug', $roleSlug));
    }

    public function supervisorAssignments(): HasMany
    {
        return $this->hasMany(ThesisSupervisor::class);
    }

    public function supervisionSessions(): HasMany
    {
        return $this->hasMany(SupervisionSession::class);
    }

    public function examinerAssignments(): HasMany
    {
        return $this->hasMany(ExamExaminer::class);
    }

    /**
     * "Dr. Ani Wijaya, M.Kom" — gelar depan/belakang around the plain name.
     */
    public function getFullNameAttribute(): string
    {
        return trim(collect([$this->gelar_depan, $this->name])->filter()->join(' ')).
            ($this->gelar_belakang ? ', '.$this->gelar_belakang : '');
    }
}
