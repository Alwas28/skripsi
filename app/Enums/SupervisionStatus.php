<?php

namespace App\Enums;

/**
 * Supervision session flow, blueprint.pdf §6.
 */
enum SupervisionStatus: string
{
    case Requested = 'requested';
    case Reviewed = 'reviewed';
    case RevisionRequired = 'revision_required';
    case Resubmitted = 'resubmitted';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Menunggu Review',
            self::Reviewed => 'Sudah Direview',
            self::RevisionRequired => 'Perlu Revisi',
            self::Resubmitted => 'Revisi Dikirim',
            self::Approved => 'Disetujui',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Requested, self::Resubmitted => 'badge-blue',
            self::Reviewed => 'badge-gray',
            self::RevisionRequired => 'badge-amber',
            self::Approved => 'badge-green',
        };
    }

    public function awaitsLecturer(): bool
    {
        return in_array($this, [self::Requested, self::Reviewed, self::Resubmitted], true);
    }
}
