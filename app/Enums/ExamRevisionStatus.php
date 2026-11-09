<?php

namespace App\Enums;

/**
 * Exam revision item flow, blueprint.pdf §13. Only Approved closes an item.
 */
enum ExamRevisionStatus: string
{
    case Open = 'open';
    case Submitted = 'submitted';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Submitted => 'Menunggu Validasi',
            self::Approved => 'Disetujui',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'badge-amber',
            self::Submitted => 'badge-blue',
            self::Approved => 'badge-green',
        };
    }
}
