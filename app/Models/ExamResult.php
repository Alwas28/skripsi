<?php

namespace App\Models;

use App\Enums\ExamDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_attempt_id', 'final_score', 'decision', 'decided_by', 'decided_at'])]
class ExamResult extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'final_score' => 'float',
            'decision' => ExamDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
