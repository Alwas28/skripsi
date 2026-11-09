<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Features with the standard CRUD actions ("aksi.fitur", e.g. tambah.user).
     * This and VIEW_ONLY_FEATURES are the single place permissions are
     * registered.
     */
    private const FEATURES = [
        'user' => 'Manajemen User',
        'role' => 'Role & Akses',
        'faculty' => 'Fakultas',
        'study_program' => 'Program Studi',
        'academic_year' => 'Tahun Akademik',
        'student' => 'Mahasiswa',
        'lecturer' => 'Dosen',
        'title_submission' => 'Persetujuan Judul (Kaprodi)',
        'supervisor_assignment' => 'Penetapan Pembimbing',
        'document' => 'Dokumen',
        'room' => 'Ruang',
        'supervision' => 'Bimbingan',
        'revision' => 'Revisi',
        'approval' => 'Persetujuan Dosen',
        'schedule' => 'Jadwal',
        'examiner' => 'Penguji',
        'assessment' => 'Penilaian',
        'setting' => 'Pengaturan',
        'exam_request' => 'Pengajuan & Verifikasi Ujian',
        'exam_decision' => 'Keputusan Ujian & Validasi Akhir',
    ];

    /**
     * Features that only control whether a menu can be opened (see
     * config/menu.php). Move one to FEATURES when its module gets real
     * create/update/delete actions.
     */
    private const VIEW_ONLY_FEATURES = [
        'thesis' => 'Skripsi Saya',
        'my_title_submission' => 'Pengajuan Judul (Mahasiswa)',
        'advisor_title' => 'Persetujuan Judul (Dosen PA)',
        'supervisee' => 'Mahasiswa Bimbingan',
        'document_review' => 'Review Dokumen',
        'exam_proposal' => 'Ujian Proposal',
        'exam_result' => 'Ujian Hasil',
        'exam_thesis' => 'Ujian Skripsi',
        'examinee' => 'Mahasiswa Penguji',
        'grade' => 'Nilai',
        'minutes' => 'Berita Acara',
        'history' => 'Riwayat',
        'notification' => 'Notifikasi',
        'report' => 'Laporan',
        'monitoring' => 'Monitoring',
        'audit_log' => 'Audit Log',
    ];

    /**
     * Features admin_prodi gets full CRUD on and kaprodi gets read-only on,
     * by default — everything else (user/role management) stays ungranted
     * until an admin explicitly turns it on via Role & Akses, since those
     * are more sensitive than day-to-day master data / workflow upkeep.
     */
    private const PRODI_MANAGED_FEATURES = ['faculty', 'study_program', 'academic_year', 'student', 'lecturer', 'room'];

    /**
     * Default menu access per role, following blueprint.pdf §25. Every line
     * can be changed afterwards from Role & Akses.
     *
     * title_submission: "edit" is the Kaprodi's approval.
     * supervisor_assignment: "tambah" is Admin Prodi's proposal, "edit" is the
     * Kaprodi's decision.
     *
     * @var array<string, list<string>>
     */
    private const DEFAULT_ROLE_ACCESS = [
        'mahasiswa' => [
            'lihat.thesis', 'lihat.my_title_submission', 'lihat.supervisor_assignment', 'lihat.supervision',
            'lihat.document', 'tambah.document', 'lihat.revision', 'lihat.exam_proposal', 'lihat.exam_result', 'lihat.exam_thesis',
            'lihat.schedule', 'lihat.grade', 'lihat.minutes', 'lihat.notification',
            'tambah.supervision', 'tambah.revision', 'tambah.exam_request',
        ],
        'dosen_pa' => ['lihat.advisor_title', 'lihat.notification'],
        'dosen_pembimbing' => [
            'lihat.supervisee', 'lihat.supervision', 'lihat.document_review', 'lihat.revision', 'lihat.approval',
            'lihat.schedule', 'lihat.assessment', 'lihat.history', 'lihat.notification',
            'edit.supervision', 'edit.approval', 'edit.revision', 'edit.assessment',
        ],
        'dosen_penguji' => ['lihat.examinee', 'lihat.schedule', 'lihat.assessment', 'lihat.revision', 'lihat.history', 'lihat.notification', 'edit.assessment', 'edit.revision'],
        'admin_prodi' => [
            'lihat.title_submission', 'lihat.supervisor_assignment', 'tambah.supervisor_assignment', 'lihat.document', 'lihat.supervision',
            'lihat.exam_proposal', 'lihat.exam_result', 'lihat.exam_thesis', 'lihat.schedule', 'lihat.examiner',
            'lihat.assessment', 'lihat.revision', 'lihat.report', 'lihat.setting', 'lihat.notification',
            'edit.exam_request', 'tambah.examiner', 'edit.examiner', 'edit.schedule', 'edit.setting',
        ],
        'kaprodi' => [
            'lihat.title_submission', 'edit.title_submission', 'lihat.supervisor_assignment', 'lihat.document',
            'edit.supervisor_assignment', 'lihat.supervision', 'lihat.exam_proposal', 'lihat.exam_result',
            'lihat.exam_thesis', 'lihat.schedule', 'lihat.assessment', 'lihat.revision', 'lihat.report', 'lihat.monitoring', 'lihat.notification', 'edit.exam_decision',
        ],
    ];

    private const ACTIONS = [
        'tambah' => 'Tambah',
        'lihat' => 'Lihat',
        'edit' => 'Edit',
        'hapus' => 'Hapus',
    ];

    /**
     * Roles present out of the box. `slug` must match config/menu.php's
     * menu-group mapping expectations for mahasiswa/dosen/prodi.
     */
    private const ROLES = [
        ['slug' => 'administrator', 'name' => 'Administrator', 'menu_group' => 'prodi', 'is_super_admin' => true, 'description' => 'Akses penuh ke seluruh sistem, termasuk pengaturan role & akses.'],
        ['slug' => 'mahasiswa', 'name' => 'Mahasiswa', 'menu_group' => 'mahasiswa', 'is_super_admin' => false, 'description' => null],
        ['slug' => 'dosen_pa', 'name' => 'Dosen PA', 'menu_group' => 'dosen', 'is_super_admin' => false, 'description' => 'Menyetujui usulan judul mahasiswa PA-nya.'],
        ['slug' => 'dosen_pembimbing', 'name' => 'Dosen Pembimbing', 'menu_group' => 'dosen', 'is_super_admin' => false, 'description' => 'Membimbing mahasiswa sebagai Pembimbing 1 atau 2.'],
        ['slug' => 'dosen_penguji', 'name' => 'Dosen Penguji', 'menu_group' => 'dosen', 'is_super_admin' => false, 'description' => 'Menguji dan menilai ujian mahasiswa.'],
        ['slug' => 'admin_prodi', 'name' => 'Admin Prodi', 'menu_group' => 'prodi', 'is_super_admin' => false, 'description' => null],
        ['slug' => 'kaprodi', 'name' => 'Kepala Program Studi', 'menu_group' => 'prodi', 'is_super_admin' => false, 'description' => null],
    ];

    public function run(): void
    {
        // ->values() on the inner map matters: ACTIONS keys ('tambah',
        // 'lihat', ...) repeat across every feature, and flatMap's collapse
        // merges by key — without reindexing, each feature's permissions
        // would overwrite the previous feature's at the same action key.
        $permissions = collect(self::FEATURES)->flatMap(
            fn (string $featureLabel, string $feature) => collect(self::ACTIONS)
                ->map(fn (string $actionLabel, string $action) => $this->registerPermission($feature, $featureLabel, $action, $actionLabel))
                ->values()
        )->concat(collect(self::VIEW_ONLY_FEATURES)->map(
            fn (string $featureLabel, string $feature) => $this->registerPermission($feature, $featureLabel, 'lihat', self::ACTIONS['lihat'])
        )->values());

        $roles = collect(self::ROLES)->map(
            fn (array $attributes) => Role::updateOrCreate(['slug' => $attributes['slug']], $attributes)
        )->keyBy('slug');

        // Administrator gets every permission explicitly too (on top of the
        // is_super_admin bypass) so its switches read as "all on" in the UI.
        $roles['administrator']->permissions()->sync($permissions->pluck('id'));

        // syncWithoutDetaching (not sync): re-running this seeder after
        // adding a feature must not clobber permissions an admin already
        // hand-tuned via Role & Akses — it only ever adds these defaults,
        // never removes a grant/revoke someone made afterwards.
        $prodiManagedPermissions = $permissions->whereIn('feature', self::PRODI_MANAGED_FEATURES);
        $roles['admin_prodi']->permissions()->syncWithoutDetaching($prodiManagedPermissions->pluck('id'));
        $roles['kaprodi']->permissions()->syncWithoutDetaching(
            $prodiManagedPermissions->where('action', 'lihat')->pluck('id')
        );

        foreach (self::DEFAULT_ROLE_ACCESS as $slug => $permissionNames) {
            $roles[$slug]->permissions()->syncWithoutDetaching(
                $permissions->whereIn('name', $permissionNames)->pluck('id')
            );
        }
    }

    private function registerPermission(string $feature, string $featureLabel, string $action, string $actionLabel): Permission
    {
        return Permission::updateOrCreate(
            ['name' => "{$action}.{$feature}"],
            [
                'action' => $action,
                'action_label' => $actionLabel,
                'feature' => $feature,
                'feature_label' => $featureLabel,
            ],
        );
    }
}
