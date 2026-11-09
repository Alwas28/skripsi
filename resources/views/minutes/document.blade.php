<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Acara {{ $attempt->minutesNumber() }}</title>
    <style>
        body { font-family: Georgia, 'Times New Roman', serif; color: #111; background: #fff; max-width: 720px; margin: 32px auto; padding: 0 24px; line-height: 1.6; }
        h1 { font-size: 20px; text-align: center; margin-bottom: 4px; }
        .number { text-align: center; font-size: 13px; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; font-size: 14px; }
        th, td { border: 1px solid #333; padding: 6px 10px; text-align: left; }
        .plain td { border: none; padding: 2px 6px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    @php $thesis = $attempt->exam->thesis; @endphp
    <h1>BERITA ACARA {{ strtoupper($attempt->kind()->label()) }}</h1>
    <p class="number">Nomor: {{ $attempt->minutesNumber() }}</p>

    <table class="plain">
        <tr><td style="width:170px">Nama Mahasiswa</td><td>: {{ $thesis->student->name }}</td></tr>
        <tr><td>NIM</td><td>: {{ $thesis->student->nim }}</td></tr>
        <tr><td>Program Studi</td><td>: {{ $thesis->student->studyProgram->name ?? '-' }}</td></tr>
        <tr><td>Judul</td><td>: {{ $thesis->title?->title }}</td></tr>
        <tr><td>Percobaan Ke</td><td>: {{ $attempt->attempt_number }}</td></tr>
        <tr><td>Tanggal</td><td>: {{ $attempt->schedule->exam_date->format('d-m-Y') }}, pukul {{ $attempt->schedule->timeRange() }}</td></tr>
        <tr><td>Ruang</td><td>: {{ $attempt->schedule->room->name }}</td></tr>
    </table>

    <table>
        <thead><tr><th>Penilai</th><th>Nama</th><th>Nilai</th></tr></thead>
        <tbody>
            @foreach ($attempt->assessments->sortBy('assessor_role') as $assessment)
                <tr>
                    <td>{{ \App\Models\ExamAttempt::assessorLabel($assessment->assessor_role) }}</td>
                    <td>{{ $assessment->lecturer->full_name }}</td>
                    <td>{{ number_format($assessment->score, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p><strong>Nilai Akhir:</strong> {{ number_format($attempt->result->final_score, 2) }}<br>
    <strong>Keputusan:</strong> {{ $attempt->result->decision->label() }}<br>
    <strong>Tanggal Keputusan:</strong> {{ $attempt->result->decided_at->format('d-m-Y H:i') }}</p>
</body>
</html>
