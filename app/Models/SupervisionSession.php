<?php

namespace App\Models;

use App\Enums\ExamKind;
use App\Enums\SupervisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['thesis_id', 'lecturer_id', 'phase', 'chapter', 'topic', 'session_date', 'status', 'approved_at'])]
class SupervisionSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'phase' => ExamKind::class,
            'status' => SupervisionStatus::class,
            'session_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(SupervisionFile::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SupervisionNote::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SupervisionRevision::class);
    }
}
