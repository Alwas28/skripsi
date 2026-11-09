<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['thesis_id', 'from_status', 'to_status', 'changed_by', 'notes'])]
class ThesisStatusHistory extends Model
{
    public function thesis(): BelongsTo
    {
        return $this->belongsTo(Thesis::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
