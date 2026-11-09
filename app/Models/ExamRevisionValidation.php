<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['exam_revision_id', 'validated_by', 'decision', 'notes'])]
class ExamRevisionValidation extends Model
{
    use HasFactory;

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ExamRevision::class, 'exam_revision_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
