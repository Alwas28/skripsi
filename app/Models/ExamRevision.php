<?php

namespace App\Models;

use App\Enums\ExamRevisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exam_attempt_id', 'lecturer_id', 'chapter', 'notes', 'status', 'document_version_id'])]
class ExamRevision extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => ExamRevisionStatus::class,
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(Lecturer::class);
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }

    public function validations(): HasMany
    {
        return $this->hasMany(ExamRevisionValidation::class);
    }
}
