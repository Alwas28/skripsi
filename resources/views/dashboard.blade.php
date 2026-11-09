<x-app-layout>
    <x-slot name="header">Dashboard</x-slot>

    @if ($isStudent && ! $studentPanel)
        <div class="card animate-fadeUp" style="margin-bottom:20px">
            <div class="card-body">
                <p style="font-size:13px;color:var(--text)">Belum ada data skripsi. Mulai dari menu <a href="{{ route('mahasiswa.title-submission') }}" class="auth-link">Pengajuan Judul</a>.</p>
            </div>
        </div>
    @endif

    @if ($studentPanel)
        <div class="card animate-fadeUp" style="margin-bottom:20px">
            <div class="card-header">
                <div class="card-title">Dashboard Skripsi</div>
                <span class="badge {{ $studentPanel['thesis']->status->badgeClass() }}">{{ $studentPanel['thesis']->status->label() }}</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                @include('mahasiswa._progress', ['percent' => $studentPanel['percent'], 'milestones' => $studentPanel['milestones']])
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;font-size:13px;color:var(--text);border-top:1px solid var(--border);padding-top:14px">
                    <div>Bimbingan berikutnya: <strong>{{ $studentPanel['nextSession']?->session_date->format('d M Y') ?? '—' }}</strong></div>
                    <div>Revisi terbuka: <strong>{{ $studentPanel['openRevisions'] }}</strong></div>
                    <div>Ujian berikutnya: <strong>{{ $studentPanel['nextExam'] ? $studentPanel['nextExam']->exam_date->format('d M Y').' '.$studentPanel['nextExam']->timeRange() : '—' }}</strong></div>
                </div>
            </div>
        </div>
    @endif

    @if ($prodiPanel)
        @include('prodi._missing-study-program')
        <p class="nav-section" style="margin:0 0 10px">Monitoring Skripsi</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:20px">
            @foreach ($prodiPanel as $label => $value)
                <div class="stat-card animate-fadeUp">
                    <div>
                        <div class="stat-num">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($lecturerPanel)
        <p class="nav-section" style="margin:0 0 10px">Dashboard Dosen</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:14px;margin-bottom:20px">
            @foreach ($lecturerPanel['stats'] as $label => $value)
                <div class="stat-card animate-fadeUp">
                    <div>
                        <div class="stat-num">{{ $value }}</div>
                        <div class="stat-label">{{ $label }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($lecturerPanel['students']->isNotEmpty())
            <div class="card animate-fadeUp" style="margin-bottom:20px">
                <div class="card-header"><div class="card-title">Mahasiswa</div></div>
                <div style="overflow-x:auto">
                    <table class="tbl">
                        <thead><tr><th>Nama</th><th>Progres</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($lecturerPanel['students'] as $student)
                                <tr>
                                    <td class="td-main">{{ $student['name'] }}</td>
                                    <td>{{ $student['percent'] }}%</td>
                                    <td>{{ $student['status'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif

    @if (! $isStudent && ! $prodiPanel && ! $lecturerPanel)
        <div class="card animate-fadeUp">
            <div class="card-body">
                <p style="color:var(--text)">{{ __('Anda berhasil masuk.') }}</p>
            </div>
        </div>
    @endif
</x-app-layout>
