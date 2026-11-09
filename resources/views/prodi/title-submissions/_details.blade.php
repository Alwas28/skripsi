<div class="card">
    <div class="card-header">
        <div class="card-title">Data Pengajuan</div>
    </div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
        <div>
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">Judul Skripsi</div>
            <div style="font-size:14px;color:var(--text);margin-top:3px;font-weight:600">{{ $title->title ?: '—' }}</div>
        </div>
        <div>
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">Bidang Penelitian</div>
            <div style="font-size:13px;color:var(--text);margin-top:3px">{{ $title->research_field ?: '—' }}</div>
        </div>
        @foreach ([
            'background' => 'Latar Belakang',
            'problem_statement' => 'Rumusan Masalah',
            'objectives' => 'Tujuan',
            'method' => 'Metode',
            'research_object' => 'Objek Penelitian',
            'initial_references' => 'Referensi Awal',
        ] as $field => $label)
            <div>
                <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">{{ $label }}</div>
                <div style="font-size:13px;color:var(--text);margin-top:3px;white-space:pre-line">{{ $title->$field ?: '—' }}</div>
            </div>
        @endforeach
        <div>
            <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">Proposal Awal</div>
            @if ($title->proposal_path)
                <a href="{{ $downloadUrl }}" class="btn btn-secondary" style="margin-top:6px;display:inline-flex">
                    <i class="fa-solid fa-download"></i>Unduh Proposal
                </a>
            @else
                <div style="font-size:13px;color:var(--muted);margin-top:3px">—</div>
            @endif
        </div>
    </div>
</div>
