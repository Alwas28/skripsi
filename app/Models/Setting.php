<?php

namespace App\Models;

use App\Enums\ExamKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-study-program rules (blueprint.pdf §26) with built-in defaults, so a
 * program that never opened Pengaturan still works.
 */
#[Fillable(['study_program_id', 'key', 'value'])]
class Setting extends Model
{
    use HasFactory;

    private const DEFAULT_COMPONENTS = [
        ['name' => 'Penguasaan Materi', 'weight' => 40],
        ['name' => 'Metodologi', 'weight' => 30],
        ['name' => 'Presentasi', 'weight' => 30],
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    /**
     * @return array{min_supervisions: int, supervisor_approval: bool, document_required: bool, administration_required: bool, pass_score: float|int, examiner_weight: int, supervisor_weight: int, components: list<array{name: string, weight: int}>}
     */
    public static function examRules(int $studyProgramId, ExamKind $kind): array
    {
        $defaults = [
            'min_supervisions' => match ($kind) {
                ExamKind::Proposal => 5,
                ExamKind::Result => 10,
                ExamKind::Thesis => 0,
            },
            'supervisor_approval' => true,
            'document_required' => $kind !== ExamKind::Result,
            'administration_required' => $kind !== ExamKind::Result,
            'pass_score' => 60,
            'examiner_weight' => 70,
            'supervisor_weight' => 30,
            'components' => self::DEFAULT_COMPONENTS,
        ];

        return array_merge($defaults, static::value($studyProgramId, "exam.{$kind->value}") ?? []);
    }

    /**
     * @return array{no_supervision_days: int, max_duration_days: int}
     */
    public static function monitoringRules(int $studyProgramId): array
    {
        return array_merge(
            ['no_supervision_days' => 30, 'max_duration_days' => 365],
            static::value($studyProgramId, 'monitoring') ?? [],
        );
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function store(int $studyProgramId, string $key, array $value): void
    {
        static::updateOrCreate(['study_program_id' => $studyProgramId, 'key' => $key], ['value' => $value]);
    }

    /**
     * Turns "Penguasaan Materi:40" lines into components, or null when a line
     * is malformed or the weights don't add up to 100.
     *
     * @return list<array{name: string, weight: int}>|null
     */
    public static function parseComponents(string $lines): ?array
    {
        $components = [];

        foreach (preg_split('/\R/', trim($lines)) as $line) {
            if (! preg_match('/^(.+):\s*(\d{1,3})\s*$/', trim($line), $match) || (int) $match[2] < 1) {
                return null;
            }

            $components[] = ['name' => trim($match[1]), 'weight' => (int) $match[2]];
        }

        return array_sum(array_column($components, 'weight')) === 100 ? $components : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function value(int $studyProgramId, string $key): ?array
    {
        return static::where('study_program_id', $studyProgramId)->where('key', $key)->value('value');
    }
}
