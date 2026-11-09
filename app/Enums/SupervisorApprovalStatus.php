<?php

namespace App\Enums;

/**
 * Kaprodi's decision on a proposed Pembimbing 1 / Pembimbing 2 pair. Kept
 * apart from ThesisStatus so the §24 status list stays as the blueprint has it.
 */
enum SupervisorApprovalStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan Kaprodi',
            self::Approved => 'Ditetapkan',
            self::Rejected => 'Ditolak Kaprodi',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'badge-blue',
            self::Approved => 'badge-green',
            self::Rejected => 'badge-amber',
        };
    }
}
