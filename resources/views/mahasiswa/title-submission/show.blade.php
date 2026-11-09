<x-app-layout>
    <x-slot name="header">Pengajuan Judul</x-slot>

    @php $title = $thesis->title; @endphp

    <!-- Status -->
    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <span class="badge {{ $thesis->status->badgeClass() }}" style="font-size:12px;padding:5px 14px">
                {{ $thesis->status->label() }}
            </span>
            <span class="text-muted" style="font-size:12.5px">
                @if ($thesis->status->value === 'TITLE_APPROVED')
                    Judul Anda sudah disetujui Kaprodi. Tahap selanjutnya (penetapan pembimbing) akan diinformasikan lebih lanjut.
                @elseif ($thesis->status->value === 'SUBMITTED')
                    Pengajuan Anda sedang menunggu persetujuan Dosen PA.
                @elseif ($thesis->status->value === 'TITLE_REVIEW')
                    Dosen PA sudah menyetujui. Pengajuan Anda sedang menunggu persetujuan Kaprodi.
                @elseif ($thesis->status->value === 'REVISION_REQUIRED')
                    Pengajuan Anda perlu diperbaiki — lihat catatan di bawah, perbaiki, lalu ajukan ulang ke Dosen PA.
                @else
                    Lengkapi form di bawah lalu klik "Ajukan Judul" untuk mengirim ke Dosen PA.
                @endif
            </span>
            @if ($advisor)
                <span class="text-muted" style="font-size:12.5px;margin-left:auto">Dosen PA: {{ $advisor->full_name }}</span>
            @endif
        </div>
    </div>

    @if ($editable && ! $advisor)
        <div class="card animate-fadeUp d1" style="margin-top:20px;border-color:rgba(245,158,11,.35)">
            <div class="card-body" style="display:flex;gap:12px">
                <i class="fa-solid fa-triangle-exclamation" style="color:#fbbf24;margin-top:2px"></i>
                <p style="font-size:13px;color:var(--sub)">Dosen PA Anda belum ditentukan. Anda tetap bisa menyimpan draft, tetapi judul baru bisa diajukan setelah Admin Prodi menentukan Dosen PA.</p>
            </div>
        </div>
    @endif

    @if ($thesis->status->value === 'REVISION_REQUIRED' && $title->review_notes)
        <div class="card animate-fadeUp d1" style="margin-top:20px;border-color:rgba(245,158,11,.35)">
            <div class="card-body" style="display:flex;gap:12px">
                <i class="fa-solid fa-triangle-exclamation" style="color:#fbbf24;margin-top:2px"></i>
                <div>
                    <div style="font-size:13px;font-weight:600;color:var(--text)">Catatan Revisi</div>
                    <p style="font-size:13px;color:var(--sub);margin-top:4px;white-space:pre-line">{{ $title->review_notes }}</p>
                </div>
            </div>
        </div>
    @endif

    <div class="card animate-fadeUp d2" style="margin-top:20px">
        <div class="card-header">
            <div class="card-title">Data Pengajuan Judul</div>
        </div>

        @if ($editable)
            <form method="POST" action="{{ route('mahasiswa.title-submission.update') }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
                    <div>
                        <x-input-label for="title" value="Judul Skripsi" />
                        <x-text-input id="title" name="title" type="text" :value="old('title', $title->title)" required autofocus />
                        <x-input-error :messages="$errors->get('title')" />
                    </div>

                    <div>
                        <x-input-label for="research_field" value="Bidang Penelitian" />
                        <x-text-input id="research_field" name="research_field" type="text" :value="old('research_field', $title->research_field)" placeholder="mis. Rekayasa Perangkat Lunak" />
                        <x-input-error :messages="$errors->get('research_field')" />
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
                            <x-input-label for="{{ $field }}" value="{{ $label }}" />
                            <textarea id="{{ $field }}" name="{{ $field }}" rows="4" class="f-input">{{ old($field, $title->$field) }}</textarea>
                            <x-input-error :messages="$errors->get($field)" />
                        </div>
                    @endforeach

                    <div>
                        <x-input-label for="proposal" value="Proposal Awal (PDF/DOC, maks 10MB)" />
                        <input id="proposal" name="proposal" type="file" class="f-input" accept=".pdf,.doc,.docx">
                        @if ($title->proposal_path)
                            <p class="text-muted" style="font-size:12px;margin-top:6px">
                                <i class="fa-solid fa-paperclip"></i> Sudah ada file tersimpan —
                                <a href="{{ route('mahasiswa.title-submission.download') }}" class="auth-link">unduh</a>.
                                Unggah file baru untuk menggantinya.
                            </p>
                        @endif
                        <x-input-error :messages="$errors->get('proposal')" />
                    </div>
                </div>

                <div class="card-body" style="border-top:1px solid var(--border);display:flex;align-items:center;gap:12px">
                    <button type="submit" name="action" value="submit" class="btn btn-primary" @disabled(! $advisor)>
                        <i class="fa-solid fa-paper-plane"></i>Ajukan Judul
                    </button>
                    <button type="submit" name="action" value="draft" class="btn btn-secondary">
                        <i class="fa-solid fa-floppy-disk"></i>Simpan Draft
                    </button>
                </div>
            </form>
        @else
            <div class="card-body" style="display:flex;flex-direction:column;gap:16px">
                <div>
                    <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">Judul Skripsi</div>
                    <div style="font-size:14px;color:var(--text);margin-top:3px;font-weight:600">{{ $title->title }}</div>
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
                        <a href="{{ route('mahasiswa.title-submission.download') }}" class="btn btn-secondary" style="margin-top:6px;display:inline-flex">
                            <i class="fa-solid fa-download"></i>Unduh Proposal
                        </a>
                    @else
                        <div style="font-size:13px;color:var(--muted);margin-top:3px">—</div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
