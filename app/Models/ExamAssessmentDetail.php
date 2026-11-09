<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_assessment_id', 'component', 'weight', 'score'])]
class ExamAssessmentDetail extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'score' => 'float',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(ExamAssessment::class, 'exam_assessment_id');
    }
}
