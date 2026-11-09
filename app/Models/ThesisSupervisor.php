<?php

namespace App\Models;

use App\Enums\SupervisorApprovalStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thesis_id', 'lecturer_id', 'position', 'status', 'assigned_by', 'reviewed_by', 'reviewed_at', 'review_notes'])]
class ThesisSupervisor extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SupervisorApprovalStatus::class,
            'reviewed_at' => 'datetime',
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
