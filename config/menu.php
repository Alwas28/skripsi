<?php

/**
 * Sidebar navigation, grouped by menu group (see App\Models\User::menuGroup()),
 * following blueprint.pdf §29 "Blueprint halaman utama".
 *
 * Every item needs: key, label, icon (Font Awesome class), route (name).
 * Items without a "uri" (e.g. dashboard/profile) already have a route defined
 * elsewhere in routes/web.php. Items with a "uri" are auto-registered in
 * routes/web.php as placeholder pages via ModulePlaceholderController — real
 * data/controllers replace them module by module as the backend is built.
 *
 * "permission" ties an item to Role & Akses: a role without it neither sees
 * the item nor can open its route. The catalog and the default grants live in
 * database/seeders/RolePermissionSeeder.php. Only dashboard and profile have
 * no permission, since every account needs them. The same feature can appear
 * in several groups (e.g. lihat.supervision) — menu_group decides which page
 * a role lands on.
 *
 * This file is the single source of truth for both the sidebar (layouts/app)
 * and route registration, so the two never drift apart.
 */

return [

    'mahasiswa' => [
        'sections' => [
            [
                'label' => 'Utama',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'route' => 'dashboard'],
                    ['key' => 'thesis', 'label' => 'Skripsi Saya', 'icon' => 'fa-folder-open', 'route' => 'mahasiswa.thesis', 'permission' => 'lihat.thesis', 'description' => 'Ringkasan status skripsi Anda dari pengajuan judul sampai selesai.'],
                ],
            ],
            [
                'label' => 'Pengajuan & Bimbingan',
                'items' => [
                    ['key' => 'title-submission', 'label' => 'Pengajuan Judul', 'icon' => 'fa-file-circle-plus', 'route' => 'mahasiswa.title-submission', 'permission' => 'lihat.my_title_submission', 'description' => 'Ajukan judul skripsi beserta latar belakang, rumusan masalah, metode, dan proposal awal.'],
                    ['key' => 'supervisor', 'label' => 'Pembimbing', 'icon' => 'fa-chalkboard-user', 'route' => 'mahasiswa.supervisor', 'permission' => 'lihat.supervisor_assignment', 'description' => 'Lihat Pembimbing 1 dan Pembimbing 2 yang ditetapkan Prodi untuk skripsi Anda.'],
                    ['key' => 'supervision', 'label' => 'Bimbingan', 'icon' => 'fa-comments', 'route' => 'mahasiswa.supervision', 'permission' => 'lihat.supervision', 'description' => 'Ajukan sesi bimbingan per bab/topik, unggah dokumen, dan pantau catatan dari pembimbing.'],
                    ['key' => 'documents', 'label' => 'Dokumen', 'icon' => 'fa-file-lines', 'route' => 'mahasiswa.documents', 'permission' => 'lihat.document', 'description' => 'Semua dokumen skripsi Anda per tahapan, lengkap dengan versi dan riwayatnya.'],
                    ['key' => 'revisions', 'label' => 'Revisi', 'icon' => 'fa-pen-to-square', 'route' => 'mahasiswa.revisions', 'permission' => 'lihat.revision', 'description' => 'Daftar item revisi dari penguji/pembimbing yang perlu Anda selesaikan.'],
                ],
            ],
            [
                'label' => 'Ujian',
                'items' => [
                    ['key' => 'exam-proposal', 'label' => 'Ujian Proposal', 'icon' => 'fa-file-signature', 'route' => 'mahasiswa.exams.proposal', 'permission' => 'lihat.exam_proposal', 'description' => 'Ajukan ujian proposal setelah semua persyaratan terpenuhi, dan lihat hasilnya.'],
                    ['key' => 'exam-result', 'label' => 'Ujian Hasil', 'icon' => 'fa-flask', 'route' => 'mahasiswa.exams.result', 'permission' => 'lihat.exam_result', 'description' => 'Ajukan ujian hasil penelitian, dan lihat hasilnya.'],
                    ['key' => 'exam-thesis', 'label' => 'Ujian Skripsi', 'icon' => 'fa-graduation-cap', 'route' => 'mahasiswa.exams.thesis', 'permission' => 'lihat.exam_thesis', 'description' => 'Ajukan ujian skripsi akhir, dan lihat hasilnya.'],
                    ['key' => 'schedule', 'label' => 'Jadwal', 'icon' => 'fa-calendar-days', 'route' => 'mahasiswa.schedule', 'permission' => 'lihat.schedule', 'description' => 'Jadwal ujian Anda: tanggal, waktu, ruang, dan penguji.'],
                    ['key' => 'grades', 'label' => 'Nilai', 'icon' => 'fa-star-half-stroke', 'route' => 'mahasiswa.grades', 'permission' => 'lihat.grade', 'description' => 'Rekap nilai dari setiap ujian yang telah Anda ikuti.'],
                    ['key' => 'minutes', 'label' => 'Berita Acara', 'icon' => 'fa-stamp', 'route' => 'mahasiswa.minutes', 'permission' => 'lihat.minutes', 'description' => 'Dokumen berita acara resmi dari setiap ujian yang telah dilaksanakan.'],
                ],
            ],
            [
                'label' => 'Lainnya',
                'items' => [
                    ['key' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'fa-bell', 'route' => 'notifications.index', 'permission' => 'lihat.notification', 'description' => 'Semua notifikasi terkait proses skripsi Anda.'],
                ],
            ],
            [
                'label' => 'Akun',
                'items' => [
                    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'fa-user-gear', 'route' => 'profile.edit'],
                ],
            ],
        ],
    ],

    'dosen' => [
        'sections' => [
            [
                'label' => 'Utama',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'route' => 'dashboard'],
                    ['key' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'fa-bell', 'route' => 'notifications.index', 'permission' => 'lihat.notification'],
                ],
            ],
            [
                'label' => 'Penasehat Akademik',
                'items' => [
                    ['key' => 'advisor-titles', 'label' => 'Persetujuan Judul', 'icon' => 'fa-user-check', 'route' => 'dosen.advisor-titles.index', 'permission' => 'lihat.advisor_title', 'description' => 'Usulan judul mahasiswa PA Anda yang menunggu persetujuan.'],
                ],
            ],
            [
                'label' => 'Bimbingan',
                'items' => [
                    ['key' => 'supervisees', 'label' => 'Mahasiswa Bimbingan', 'icon' => 'fa-user-graduate', 'route' => 'dosen.supervisees', 'permission' => 'lihat.supervisee', 'description' => 'Daftar mahasiswa yang Anda bimbing beserta progres skripsinya.'],
                    ['key' => 'supervision', 'label' => 'Bimbingan', 'icon' => 'fa-comments', 'route' => 'dosen.supervision', 'permission' => 'lihat.supervision', 'description' => 'Sesi bimbingan yang diajukan mahasiswa, menunggu review Anda.'],
                    ['key' => 'document-review', 'label' => 'Review Dokumen', 'icon' => 'fa-file-circle-check', 'route' => 'dosen.document-review', 'permission' => 'lihat.document_review', 'description' => 'Dokumen yang diunggah mahasiswa bimbingan, menunggu catatan/persetujuan Anda.'],
                    ['key' => 'revisions', 'label' => 'Revisi', 'icon' => 'fa-pen-to-square', 'route' => 'dosen.revisions', 'permission' => 'lihat.revision', 'description' => 'Item revisi yang Anda berikan dan status penyelesaiannya oleh mahasiswa.'],
                    ['key' => 'approvals', 'label' => 'Persetujuan', 'icon' => 'fa-square-check', 'route' => 'dosen.approvals', 'permission' => 'lihat.approval', 'description' => 'Persetujuan yang menunggu Anda: proposal, kelayakan ujian, dan skripsi final.'],
                ],
            ],
            [
                'label' => 'Penguji',
                'items' => [
                    ['key' => 'examinees', 'label' => 'Mahasiswa Penguji', 'icon' => 'fa-users-rectangle', 'route' => 'dosen.examinees', 'permission' => 'lihat.examinee', 'description' => 'Daftar mahasiswa yang ujiannya Anda uji.'],
                    ['key' => 'exam-schedule', 'label' => 'Jadwal Ujian', 'icon' => 'fa-calendar-days', 'route' => 'dosen.exam-schedule', 'permission' => 'lihat.schedule', 'description' => 'Jadwal ujian di mana Anda bertugas sebagai penguji atau pembimbing.'],
                    ['key' => 'assessment', 'label' => 'Penilaian', 'icon' => 'fa-clipboard-list', 'route' => 'dosen.assessment', 'permission' => 'lihat.assessment', 'description' => 'Form penilaian ujian: komponen nilai, nilai, dan catatan per mahasiswa.'],
                    ['key' => 'history', 'label' => 'Riwayat', 'icon' => 'fa-clock-rotate-left', 'route' => 'dosen.history', 'permission' => 'lihat.history', 'description' => 'Riwayat lengkap bimbingan dan ujian yang pernah Anda tangani.'],
                ],
            ],
            [
                'label' => 'Akun',
                'items' => [
                    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'fa-user-gear', 'route' => 'profile.edit'],
                ],
            ],
        ],
    ],

    'prodi' => [
        'sections' => [
            [
                'label' => 'Utama',
                'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'route' => 'dashboard'],
                    ['key' => 'notifications', 'label' => 'Notifikasi', 'icon' => 'fa-bell', 'route' => 'notifications.index', 'permission' => 'lihat.notification'],
                ],
            ],
            [
                'label' => 'Data Master',
                'items' => [
                    ['key' => 'academic-years', 'label' => 'Tahun Akademik', 'icon' => 'fa-calendar', 'route' => 'prodi.academic-years.index', 'permission' => 'lihat.academic_year'],
                    ['key' => 'faculties', 'label' => 'Fakultas', 'icon' => 'fa-building-columns', 'route' => 'prodi.faculties.index', 'permission' => 'lihat.faculty'],
                    ['key' => 'study-programs', 'label' => 'Program Studi', 'icon' => 'fa-sitemap', 'route' => 'prodi.study-programs.index', 'permission' => 'lihat.study_program'],
                    ['key' => 'students', 'label' => 'Mahasiswa', 'icon' => 'fa-user-graduate', 'route' => 'prodi.students.index', 'permission' => 'lihat.student'],
                    ['key' => 'lecturers', 'label' => 'Dosen', 'icon' => 'fa-chalkboard-user', 'route' => 'prodi.lecturers.index', 'permission' => 'lihat.lecturer'],
                    ['key' => 'rooms', 'label' => 'Ruang', 'icon' => 'fa-door-open', 'route' => 'prodi.rooms.index', 'permission' => 'lihat.room'],
                ],
            ],
            [
                'label' => 'Akademik',
                'items' => [
                    ['key' => 'title-submissions', 'label' => 'Pengajuan Judul', 'icon' => 'fa-file-circle-plus', 'route' => 'prodi.title-submissions.index', 'permission' => 'lihat.title_submission', 'description' => 'Persetujuan akhir judul oleh Kaprodi setelah disetujui Dosen PA.'],
                    ['key' => 'supervisors', 'label' => 'Pembimbing', 'icon' => 'fa-people-arrows', 'route' => 'prodi.supervisors', 'permission' => 'lihat.supervisor_assignment', 'description' => 'Penetapan Pembimbing 1 dan 2 untuk judul yang telah disetujui.'],
                    ['key' => 'documents', 'label' => 'Dokumen', 'icon' => 'fa-file-lines', 'route' => 'prodi.documents', 'permission' => 'lihat.document', 'description' => 'Dokumen skripsi mahasiswa per tahapan, lengkap dengan versi dan riwayatnya.'],
                    ['key' => 'supervision', 'label' => 'Bimbingan', 'icon' => 'fa-comments', 'route' => 'prodi.supervision', 'permission' => 'lihat.supervision', 'description' => 'Pantau aktivitas bimbingan seluruh mahasiswa di program studi.'],
                ],
            ],
            [
                'label' => 'Ujian',
                'items' => [
                    ['key' => 'exam-proposal', 'label' => 'Ujian Proposal', 'icon' => 'fa-file-signature', 'route' => 'prodi.exams.proposal', 'permission' => 'lihat.exam_proposal', 'description' => 'Verifikasi syarat, tentukan penguji, dan jadwalkan ujian proposal.'],
                    ['key' => 'exam-result', 'label' => 'Ujian Hasil', 'icon' => 'fa-flask', 'route' => 'prodi.exams.result', 'permission' => 'lihat.exam_result', 'description' => 'Verifikasi syarat, tentukan penguji, dan jadwalkan ujian hasil penelitian.'],
                    ['key' => 'exam-thesis', 'label' => 'Ujian Skripsi', 'icon' => 'fa-graduation-cap', 'route' => 'prodi.exams.thesis', 'permission' => 'lihat.exam_thesis', 'description' => 'Verifikasi syarat, tentukan penguji, dan jadwalkan ujian skripsi akhir.'],
                    ['key' => 'schedule', 'label' => 'Jadwal', 'icon' => 'fa-calendar-days', 'route' => 'prodi.schedule', 'permission' => 'lihat.schedule', 'description' => 'Kalender seluruh jadwal ujian dengan deteksi bentrok mahasiswa/pembimbing/penguji/ruang.'],
                    ['key' => 'examiners', 'label' => 'Penguji', 'icon' => 'fa-user-tie', 'route' => 'prodi.examiners', 'permission' => 'lihat.examiner', 'description' => 'Penugasan dosen penguji untuk setiap ujian.'],
                    ['key' => 'assessment', 'label' => 'Penilaian', 'icon' => 'fa-clipboard-list', 'route' => 'prodi.assessment', 'permission' => 'lihat.assessment', 'description' => 'Rekap nilai ujian dari seluruh penguji dan pembimbing.'],
                    ['key' => 'revisions', 'label' => 'Revisi', 'icon' => 'fa-pen-to-square', 'route' => 'prodi.revisions', 'permission' => 'lihat.revision', 'description' => 'Pantau status revisi ujian seluruh mahasiswa hingga selesai.'],
                ],
            ],
            [
                'label' => 'Laporan & Monitoring',
                'items' => [
                    ['key' => 'reports', 'label' => 'Laporan', 'icon' => 'fa-chart-line', 'route' => 'prodi.reports', 'permission' => 'lihat.report', 'description' => 'Laporan mahasiswa, dosen, ujian, dan statistik program studi.'],
                    ['key' => 'monitoring', 'label' => 'Monitoring', 'icon' => 'fa-triangle-exclamation', 'route' => 'prodi.monitoring', 'permission' => 'lihat.monitoring', 'description' => 'Early warning mahasiswa bermasalah: tidak bimbingan lama, revisi menumpuk, ujian tidak lulus, melebihi batas waktu.'],
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    ['key' => 'settings', 'label' => 'Pengaturan', 'icon' => 'fa-sliders', 'route' => 'prodi.settings', 'permission' => 'lihat.setting', 'description' => 'Persyaratan ujian yang configurable (minimal bimbingan, kelengkapan dokumen, dsb) tanpa mengubah kode.'],
                    ['key' => 'audit-log', 'label' => 'Audit Log', 'icon' => 'fa-shield-halved', 'route' => 'prodi.audit-log', 'permission' => 'lihat.audit_log', 'description' => 'Jejak audit setiap aktivitas penting: siapa, aksi apa, data apa, nilai lama ke nilai baru, kapan.'],
                ],
            ],
            [
                'label' => 'Kontrol Akses',
                'items' => [
                    ['key' => 'users', 'label' => 'Manajemen User', 'icon' => 'fa-users-gear', 'route' => 'users.index', 'permission' => 'lihat.user'],
                    ['key' => 'roles', 'label' => 'Role & Akses', 'icon' => 'fa-user-shield', 'route' => 'roles.index', 'permission' => 'lihat.role'],
                ],
            ],
            [
                'label' => 'Akun',
                'items' => [
                    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'fa-user-gear', 'route' => 'profile.edit'],
                ],
            ],
        ],
    ],

];
