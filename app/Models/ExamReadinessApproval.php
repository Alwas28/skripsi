<?php

namespace App\Models;

use App\Enums\ExamKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thesis_id', 'exam_kind', 'attempt_number', 'lecturer_id'])]
class ExamReadinessApproval extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'exam_kind' => ExamKind::class,
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
}
