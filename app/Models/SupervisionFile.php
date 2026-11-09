<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['supervision_session_id', 'document_version_id'])]
class SupervisionFile extends Model
{
    use HasFactory;

    public function session(): BelongsTo
    {
        return $this->belongsTo(SupervisionSession::class, 'supervision_session_id');
    }

    public function documentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class);
    }
}
