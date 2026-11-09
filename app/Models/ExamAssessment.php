<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['exam_attempt_id', 'lecturer_id', 'assessor_role', 'score', 'notes'])]
class ExamAssessment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score' => 'float',
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

    public function details(): HasMany
    {
        return $this->hasMany(ExamAssessmentDetail::class);
    }
}
