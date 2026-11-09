<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'user_name', 'action', 'module', 'record_type', 'record_id', 'old_values', 'new_values', 'ip_address'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * Every audited model and its module name. AppServiceProvider attaches
     * AuditObserver to each model listed here.
     *
     * @var array<class-string<Model>, string>
     */
    public const MODULES = [
        Thesis::class => 'Skripsi',
        ThesisTitle::class => 'Judul Skripsi',
        ThesisSupervisor::class => 'Pembimbing',
        SupervisionSession::class => 'Bimbingan',
        Exam::class => 'Ujian',
        ExamAttempt::class => 'Pengajuan Ujian',
        ExamExaminer::class => 'Penguji',
        ExamSchedule::class => 'Jadwal Ujian',
        ExamAssessment::class => 'Penilaian',
        ExamResult::class => 'Keputusan Ujian',
        ExamRevision::class => 'Revisi Ujian',
        ExamReadinessApproval::class => 'Persetujuan Kelayakan Ujian',
        Room::class => 'Ruang',
        Setting::class => 'Pengaturan',
        Document::class => 'Dokumen',
        DocumentVersion::class => 'Versi Dokumen',
        Student::class => 'Mahasiswa',
        Lecturer::class => 'Dosen',
        Faculty::class => 'Fakultas',
        StudyProgram::class => 'Program Studi',
        AcademicYear::class => 'Tahun Akademik',
        User::class => 'User',
        Role::class => 'Role & Akses',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function record(string $action, Model $model, array $oldValues = [], array $newValues = []): void
    {
        $actor = auth()->user();

        static::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name ?? 'Sistem',
            'action' => $action,
            'module' => self::MODULES[$model::class] ?? class_basename($model),
            'record_type' => class_basename($model),
            'record_id' => $model->getKey(),
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => request()->ip(),
        ]);
    }
}
