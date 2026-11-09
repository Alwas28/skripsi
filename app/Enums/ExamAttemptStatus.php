<?php

namespace App\Enums;

enum ExamAttemptStatus: string
{
    case Requested = 'requested';
    case Rejected = 'rejected';
    case Verified = 'verified';
    case Scheduled = 'scheduled';
    case Decided = 'decided';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Menunggu Verifikasi',
            self::Rejected => 'Ditolak Prodi',
            self::Verified => 'Terverifikasi',
            self::Scheduled => 'Terjadwal',
            self::Decided => 'Selesai',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Requested => 'badge-blue',
            self::Rejected => 'badge-amber',
            self::Verified, self::Scheduled => 'badge-blue',
            self::Decided => 'badge-green',
        };
    }
}
