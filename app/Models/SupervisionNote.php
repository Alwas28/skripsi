<?php

namespace App\Models;

use App\Enums\SupervisionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supervision_session_id', 'user_id', 'decision', 'note'])]
class SupervisionNote extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'decision' => SupervisionStatus::class,
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupervisionSession::class, 'supervision_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
