<?php

namespace App\Enums;

/**
 * The three exam outcomes of blueprint.pdf §8.
 */
enum ExamDecision: string
{
    case Passed = 'passed';
    case Revision = 'revision';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Passed => 'Lulus',
            self::Revision => 'Lulus dengan Revisi',
            self::Failed => 'Tidak Lulus',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Passed => 'badge-green',
            self::Revision => 'badge-amber',
            self::Failed => 'badge-red',
        };
    }
}
