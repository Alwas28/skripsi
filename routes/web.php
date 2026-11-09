<?php

use App\Http\Controllers\AcademicYearController;
use App\Http\Controllers\AdvisorTitleApprovalController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\ForcedPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamRevisionController;
use App\Http\Controllers\ExamSettingController;
use App\Http\Controllers\FacultyController;
use App\Http\Controllers\LecturerApprovalController;
use App\Http\Controllers\LecturerController;
use App\Http\Controllers\LecturerDocumentController;
use App\Http\Controllers\LecturerExamController;
use App\Http\Controllers\LecturerPortalController;
use App\Http\Controllers\LecturerSupervisionController;
use App\Http\Controllers\MinutesController;
use App\Http\Controllers\ModulePlaceholderController;
use App\Http\Controllers\MyDocumentController;
use App\Http\Controllers\MyExamController;
use App\Http\Controllers\MySupervisionController;
use App\Http\Controllers\MySupervisorController;
use App\Http\Controllers\MyThesisTitleController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProdiDocumentController;
use App\Http\Controllers\ProdiExamController;
use App\Http\Controllers\ProdiMonitorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentPortalController;
use App\Http\Controllers\StudyProgramController;
use App\Http\Controllers\SuperviseeController;
use App\Http\Controllers\SupervisorAssignmentController;
use App\Http\Controllers\TitleSubmissionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/dashboard', DashboardController::class)->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// RBAC — Manajemen User & Role & Akses (see database/seeders/RolePermissionSeeder.php
// for the "aksi.fitur" permission catalog).
Route::middleware('auth')->group(function () {
    Route::get('/pengguna', [UserController::class, 'index'])->middleware('permission:lihat.user')->name('users.index');
    Route::get('/pengguna/tambah', [UserController::class, 'create'])->middleware('permission:tambah.user')->name('users.create');
    Route::post('/pengguna', [UserController::class, 'store'])->middleware('permission:tambah.user')->name('users.store');
    Route::get('/pengguna/{user}/edit', [UserController::class, 'edit'])->middleware('permission:edit.user')->name('users.edit');
    Route::put('/pengguna/{user}', [UserController::class, 'update'])->middleware('permission:edit.user')->name('users.update');
    Route::delete('/pengguna/{user}', [UserController::class, 'destroy'])->middleware('permission:hapus.user')->name('users.destroy');

    Route::get('/role', [RoleController::class, 'index'])->middleware('permission:lihat.role')->name('roles.index');
    Route::get('/role/tambah', [RoleController::class, 'create'])->middleware('permission:tambah.role')->name('roles.create');
    Route::post('/role', [RoleController::class, 'store'])->middleware('permission:tambah.role')->name('roles.store');
    Route::get('/role/{role}/edit', [RoleController::class, 'edit'])->middleware('permission:edit.role')->name('roles.edit');
    Route::put('/role/{role}', [RoleController::class, 'update'])->middleware('permission:edit.role')->name('roles.update');
    Route::delete('/role/{role}', [RoleController::class, 'destroy'])->middleware('permission:hapus.role')->name('roles.destroy');
});

// Master Data — same "aksi.fitur" permission shape as RBAC above, just
// repeated across 5 modules, so it's worth a small helper instead of
// writing the same 6 lines out longhand 5 times.
$masterDataCrud = function (string $uri, string $name, string $controller, string $feature, string $param) {
    Route::get($uri, [$controller, 'index'])->middleware("permission:lihat.{$feature}")->name("{$name}.index");
    Route::get("{$uri}/tambah", [$controller, 'create'])->middleware("permission:tambah.{$feature}")->name("{$name}.create");
    Route::post($uri, [$controller, 'store'])->middleware("permission:tambah.{$feature}")->name("{$name}.store");
    Route::get("{$uri}/{{$param}}/edit", [$controller, 'edit'])->middleware("permission:edit.{$feature}")->name("{$name}.edit");
    Route::put("{$uri}/{{$param}}", [$controller, 'update'])->middleware("permission:edit.{$feature}")->name("{$name}.update");
    Route::delete("{$uri}/{{$param}}", [$controller, 'destroy'])->middleware("permission:hapus.{$feature}")->name("{$name}.destroy");
};

Route::middleware('auth')->group(function () use ($masterDataCrud) {
    $masterDataCrud('/prodi/fakultas', 'prodi.faculties', FacultyController::class, 'faculty', 'faculty');
    $masterDataCrud('/prodi/program-studi', 'prodi.study-programs', StudyProgramController::class, 'study_program', 'studyProgram');
    $masterDataCrud('/prodi/tahun-akademik', 'prodi.academic-years', AcademicYearController::class, 'academic_year', 'academicYear');
    $masterDataCrud('/prodi/mahasiswa', 'prodi.students', StudentController::class, 'student', 'student');
    $masterDataCrud('/prodi/dosen', 'prodi.lecturers', LecturerController::class, 'lecturer', 'lecturer');
    $masterDataCrud('/prodi/ruang', 'prodi.rooms', RoomController::class, 'room', 'room');

    // Quick-connect modal on the index page (see master-data/lecturers/index.blade.php)
    Route::post('/prodi/dosen/{lecturer}/hubungkan-akun', [LecturerController::class, 'connectAccount'])
        ->middleware('permission:edit.lecturer')
        ->name('prodi.lecturers.connect-account');

    // Pengajuan Judul — Prodi review side (mahasiswa side is below, its own
    // group, since it's ownership-gated rather than permission-gated).
    Route::get('/prodi/pengajuan-judul', [TitleSubmissionController::class, 'index'])->middleware('permission:lihat.title_submission')->name('prodi.title-submissions.index');
    Route::get('/prodi/pengajuan-judul/{thesis}', [TitleSubmissionController::class, 'show'])->middleware('permission:lihat.title_submission')->name('prodi.title-submissions.show');
    Route::get('/prodi/pengajuan-judul/{thesis}/dokumen', [TitleSubmissionController::class, 'downloadProposal'])->middleware('permission:lihat.title_submission')->name('prodi.title-submissions.download');
    Route::post('/prodi/pengajuan-judul/{thesis}/setujui', [TitleSubmissionController::class, 'approve'])->middleware('permission:edit.title_submission')->name('prodi.title-submissions.approve');
    Route::post('/prodi/pengajuan-judul/{thesis}/revisi', [TitleSubmissionController::class, 'requestRevision'])->middleware('permission:edit.title_submission')->name('prodi.title-submissions.request-revision');

    // Penetapan Pembimbing — "tambah" is Admin Prodi's proposal, "edit" is
    // the Kaprodi's decision.
    Route::get('/prodi/pembimbing', [SupervisorAssignmentController::class, 'index'])->middleware('permission:lihat.supervisor_assignment')->name('prodi.supervisors');
    Route::get('/prodi/pembimbing/{thesis}', [SupervisorAssignmentController::class, 'show'])->middleware('permission:lihat.supervisor_assignment')->name('prodi.supervisors.show');
    Route::post('/prodi/pembimbing/{thesis}', [SupervisorAssignmentController::class, 'store'])->middleware('permission:tambah.supervisor_assignment')->name('prodi.supervisors.store');
    Route::post('/prodi/pembimbing/{thesis}/setujui', [SupervisorAssignmentController::class, 'approve'])->middleware('permission:edit.supervisor_assignment')->name('prodi.supervisors.approve');
    Route::post('/prodi/pembimbing/{thesis}/tolak', [SupervisorAssignmentController::class, 'reject'])->middleware('permission:edit.supervisor_assignment')->name('prodi.supervisors.reject');
});

Route::middleware(['auth', 'permission:lihat.supervisor_assignment'])->get('/mahasiswa/pembimbing', MySupervisorController::class)->name('mahasiswa.supervisor');
Route::middleware(['auth', 'permission:lihat.supervisee'])->get('/dosen/mahasiswa-bimbingan', SuperviseeController::class)->name('dosen.supervisees');

// Pengajuan Judul — Mahasiswa side: a single show/update page (every
// student has at most one thesis). The permission opens the menu; the
// controller then limits it to the student's own record.
Route::middleware(['auth', 'permission:lihat.my_title_submission'])->group(function () {
    Route::get('/mahasiswa/pengajuan-judul', [MyThesisTitleController::class, 'show'])->name('mahasiswa.title-submission');
    Route::put('/mahasiswa/pengajuan-judul', [MyThesisTitleController::class, 'update'])->name('mahasiswa.title-submission.update');
    Route::get('/mahasiswa/pengajuan-judul/dokumen', [MyThesisTitleController::class, 'downloadProposal'])->name('mahasiswa.title-submission.download');
});

// Persetujuan Judul — Dosen PA side: the permission opens the menu, the
// controller then limits it to the students this lecturer advises.
Route::middleware(['auth', 'permission:lihat.advisor_title'])->group(function () {
    Route::get('/dosen/persetujuan-judul', [AdvisorTitleApprovalController::class, 'index'])->name('dosen.advisor-titles.index');
    Route::get('/dosen/persetujuan-judul/{thesis}', [AdvisorTitleApprovalController::class, 'show'])->name('dosen.advisor-titles.show');
    Route::get('/dosen/persetujuan-judul/{thesis}/dokumen', [AdvisorTitleApprovalController::class, 'downloadProposal'])->name('dosen.advisor-titles.download');
    Route::post('/dosen/persetujuan-judul/{thesis}/setujui', [AdvisorTitleApprovalController::class, 'approve'])->name('dosen.advisor-titles.approve');
    Route::post('/dosen/persetujuan-judul/{thesis}/revisi', [AdvisorTitleApprovalController::class, 'requestRevision'])->name('dosen.advisor-titles.request-revision');
});

Route::middleware(['auth', 'permission:lihat.audit_log'])->get('/prodi/audit-log', AuditLogController::class)->name('prodi.audit-log');

// Dokumen — Mahasiswa side: permissions open the page and the upload, the
// controller then limits everything to the student's own thesis.
Route::middleware('auth')->group(function () {
    Route::get('/mahasiswa/dokumen', [MyDocumentController::class, 'index'])->middleware('permission:lihat.document')->name('mahasiswa.documents');
    Route::post('/mahasiswa/dokumen', [MyDocumentController::class, 'store'])->middleware('permission:tambah.document')->name('mahasiswa.documents.store');
    Route::post('/mahasiswa/dokumen/{document}/versi', [MyDocumentController::class, 'storeVersion'])->middleware('permission:tambah.document')->name('mahasiswa.documents.versions.store');
    Route::get('/mahasiswa/dokumen/versi/{documentVersion}/unduh', [MyDocumentController::class, 'download'])->middleware('permission:lihat.document')->name('mahasiswa.documents.versions.download');
});

Route::middleware(['auth', 'permission:lihat.document'])->group(function () {
    Route::get('/prodi/dokumen', [ProdiDocumentController::class, 'index'])->name('prodi.documents');
    Route::get('/prodi/dokumen/{thesis}', [ProdiDocumentController::class, 'show'])->name('prodi.documents.show');
    Route::get('/prodi/dokumen/versi/{documentVersion}/unduh', [ProdiDocumentController::class, 'download'])->name('prodi.documents.versions.download');
});

Route::middleware(['auth', 'permission:lihat.notification'])->group(function () {
    Route::get('/notifikasi', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifikasi/baca-semua', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifikasi/{notification}/baca', [NotificationController::class, 'read'])->name('notifications.read');
});

Route::middleware('auth')->group(function () {
    Route::get('/prodi/pengaturan', [ExamSettingController::class, 'edit'])->middleware('permission:lihat.setting')->name('prodi.settings');
    Route::put('/prodi/pengaturan', [ExamSettingController::class, 'update'])->middleware('permission:edit.setting')->name('prodi.settings.update');
});

// Bimbingan — permissions open the pages, controllers then limit each user
// to their own students or sessions.
Route::middleware('auth')->group(function () {
    Route::get('/mahasiswa/bimbingan', [MySupervisionController::class, 'index'])->middleware('permission:lihat.supervision')->name('mahasiswa.supervision');
    Route::post('/mahasiswa/bimbingan', [MySupervisionController::class, 'store'])->middleware('permission:tambah.supervision')->name('mahasiswa.supervision.store');
    Route::post('/mahasiswa/bimbingan/{supervisionSession}/revisi', [MySupervisionController::class, 'revise'])->middleware('permission:tambah.supervision')->name('mahasiswa.supervision.revise');

    Route::get('/dosen/bimbingan', [LecturerSupervisionController::class, 'index'])->middleware('permission:lihat.supervision')->name('dosen.supervision');
    Route::get('/dosen/bimbingan/{supervisionSession}', [LecturerSupervisionController::class, 'show'])->middleware('permission:lihat.supervision')->name('dosen.supervision.show');
    Route::post('/dosen/bimbingan/{supervisionSession}/review', [LecturerSupervisionController::class, 'review'])->middleware('permission:edit.supervision')->name('dosen.supervision.review');

    Route::get('/dosen/review-dokumen', [LecturerDocumentController::class, 'index'])->middleware('permission:lihat.document_review')->name('dosen.document-review');
    Route::get('/dosen/review-dokumen/{thesis}', [LecturerDocumentController::class, 'show'])->middleware('permission:lihat.document_review')->name('dosen.document-review.show');
    Route::post('/dosen/review-dokumen/versi/{documentVersion}', [LecturerDocumentController::class, 'review'])->middleware('permission:edit.supervision')->name('dosen.document-review.review');
    Route::get('/dosen/review-dokumen/versi/{documentVersion}/unduh', [LecturerDocumentController::class, 'download'])->middleware('permission:lihat.document_review')->name('dosen.document-review.download');

    Route::get('/dosen/persetujuan', [LecturerApprovalController::class, 'index'])->middleware('permission:lihat.approval')->name('dosen.approvals');
    Route::post('/dosen/persetujuan/{thesis}/{examKind}', [LecturerApprovalController::class, 'approve'])->middleware('permission:edit.approval')->name('dosen.approvals.approve');

    Route::get('/prodi/bimbingan', [ProdiMonitorController::class, 'supervision'])->middleware('permission:lihat.supervision')->name('prodi.supervision');
});

// Ujian — one flow shared by the three exams. Permissions open each step, the
// controllers then limit it to the user's own students or study program.
Route::middleware('auth')->group(function () {
    foreach (['proposal' => 'ujian-proposal', 'result' => 'ujian-hasil', 'thesis' => 'ujian-skripsi'] as $examKind => $uri) {
        Route::get("/mahasiswa/{$uri}", [MyExamController::class, 'show'])->defaults('examKind', $examKind)->middleware("permission:lihat.exam_{$examKind}")->name("mahasiswa.exams.{$examKind}");
        Route::get("/prodi/{$uri}", [ProdiExamController::class, 'index'])->defaults('examKind', $examKind)->middleware("permission:lihat.exam_{$examKind}")->name("prodi.exams.{$examKind}");
    }

    Route::post('/mahasiswa/ujian/{examKind}/ajukan', [MyExamController::class, 'request'])->middleware('permission:tambah.exam_request')->name('mahasiswa.exams.request');

    Route::get('/prodi/ujian/{examAttempt}', [ProdiExamController::class, 'show'])->name('prodi.exams.attempt');
    Route::post('/prodi/ujian/{examAttempt}/verifikasi', [ProdiExamController::class, 'verify'])->middleware('permission:edit.exam_request')->name('prodi.exams.verify');
    Route::post('/prodi/ujian/{examAttempt}/penguji', [ProdiExamController::class, 'assignExaminers'])->middleware('permission:edit.examiner')->name('prodi.exams.examiners');
    Route::post('/prodi/ujian/{examAttempt}/jadwal', [ProdiExamController::class, 'schedule'])->middleware('permission:edit.schedule')->name('prodi.exams.schedule');
    Route::post('/prodi/ujian/{examAttempt}/keputusan', [ProdiExamController::class, 'decide'])->middleware('permission:edit.exam_decision')->name('prodi.exams.decide');
    Route::post('/prodi/skripsi/{thesis}/validasi-akhir', [ProdiExamController::class, 'finalValidate'])->middleware('permission:edit.exam_decision')->name('prodi.exams.final-validation');

    Route::get('/dosen/mahasiswa-penguji', [LecturerExamController::class, 'examinees'])->middleware('permission:lihat.examinee')->name('dosen.examinees');
    Route::get('/dosen/penilaian', [LecturerExamController::class, 'index'])->middleware('permission:lihat.assessment')->name('dosen.assessment');
    Route::get('/dosen/penilaian/{examAttempt}', [LecturerExamController::class, 'show'])->middleware('permission:lihat.assessment')->name('dosen.assessment.show');
    Route::post('/dosen/penilaian/{examAttempt}', [LecturerExamController::class, 'store'])->middleware('permission:edit.assessment')->name('dosen.assessment.store');

    Route::get('/mahasiswa/revisi', [ExamRevisionController::class, 'studentIndex'])->middleware('permission:lihat.revision')->name('mahasiswa.revisions');
    Route::post('/mahasiswa/revisi/{examRevision}', [ExamRevisionController::class, 'submit'])->middleware('permission:tambah.revision')->name('mahasiswa.revisions.submit');
    Route::get('/dosen/revisi', [ExamRevisionController::class, 'lecturerIndex'])->middleware('permission:lihat.revision')->name('dosen.revisions');
    Route::post('/dosen/revisi/{examRevision}/validasi', [ExamRevisionController::class, 'validateRevision'])->middleware('permission:edit.revision')->name('dosen.revisions.validate');
    Route::get('/dosen/revisi/{examRevision}/unduh', [ExamRevisionController::class, 'download'])->middleware('permission:lihat.revision')->name('dosen.revisions.download');

    Route::get('/berita-acara/{examAttempt}', [MinutesController::class, 'show'])->name('minutes.show');
});

// Ringkasan, jadwal, laporan, dan monitoring — read-only.
Route::middleware('auth')->group(function () {
    Route::get('/mahasiswa/skripsi', [StudentPortalController::class, 'thesis'])->middleware('permission:lihat.thesis')->name('mahasiswa.thesis');
    Route::get('/mahasiswa/jadwal', [StudentPortalController::class, 'schedule'])->middleware('permission:lihat.schedule')->name('mahasiswa.schedule');
    Route::get('/mahasiswa/nilai', [StudentPortalController::class, 'grades'])->middleware('permission:lihat.grade')->name('mahasiswa.grades');
    Route::get('/mahasiswa/berita-acara', [StudentPortalController::class, 'minutes'])->middleware('permission:lihat.minutes')->name('mahasiswa.minutes');

    Route::get('/dosen/jadwal-ujian', [LecturerPortalController::class, 'schedule'])->middleware('permission:lihat.schedule')->name('dosen.exam-schedule');
    Route::get('/dosen/riwayat', [LecturerPortalController::class, 'history'])->middleware('permission:lihat.history')->name('dosen.history');

    Route::get('/prodi/jadwal', [ProdiMonitorController::class, 'schedule'])->middleware('permission:lihat.schedule')->name('prodi.schedule');
    Route::get('/prodi/penguji', [ProdiMonitorController::class, 'examiners'])->middleware('permission:lihat.examiner')->name('prodi.examiners');
    Route::get('/prodi/penilaian', [ProdiMonitorController::class, 'assessment'])->middleware('permission:lihat.assessment')->name('prodi.assessment');
    Route::get('/prodi/revisi', [ProdiMonitorController::class, 'revisions'])->middleware('permission:lihat.revision')->name('prodi.revisions');
    Route::get('/prodi/laporan', [ProdiMonitorController::class, 'reports'])->middleware('permission:lihat.report')->name('prodi.reports');
    Route::get('/prodi/monitoring', [ProdiMonitorController::class, 'monitoring'])->middleware('permission:lihat.monitoring')->name('prodi.monitoring');
});

Route::middleware('auth')->group(function () {
    Route::get('/ubah-password', [ForcedPasswordController::class, 'edit'])->name('password.force');
    Route::put('/ubah-password', [ForcedPasswordController::class, 'update'])->name('password.force.update');
});

// Auto-register every menu item from config/menu.php that isn't wired to a
// real controller yet (i.e. still carries a "uri"). Once a module gets its
// own controller/routes, drop its "uri" from config/menu.php and register
// its real routes above instead — the sidebar keeps working unchanged.
Route::middleware('auth')->group(function () {
    foreach (config('menu') as $group) {
        foreach ($group['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if (isset($item['uri'])) {
                    Route::get($item['uri'], ModulePlaceholderController::class)
                        ->name($item['route'])
                        ->middleware(isset($item['permission']) ? ["permission:{$item['permission']}"] : []);
                }
            }
        }
    }
});

require __DIR__.'/auth.php';
