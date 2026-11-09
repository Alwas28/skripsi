<?php

namespace App\Enums;

enum DocumentReviewStatus: string
{
    case Approved = 'approved';
    case NeedsRevision = 'needs_revision';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'Disetujui',
            self::NeedsRevision => 'Perlu Perbaikan',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved => 'badge-green',
            self::NeedsRevision => 'badge-amber',
        };
    }
}
