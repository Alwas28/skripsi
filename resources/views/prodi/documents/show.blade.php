<x-app-layout>
    <x-slot name="header">Dokumen Mahasiswa</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $thesis->student->name }}</div>
                <div class="text-muted" style="font-size:12.5px;margin-top:2px">{{ $thesis->student->nim }} &middot; {{ $thesis->student->studyProgram->name ?? '—' }}</div>
                <div style="font-size:13px;color:var(--text);margin-top:8px;font-weight:600">{{ $thesis->title?->title ?: '—' }}</div>
            </div>
            <span class="badge {{ $thesis->status->badgeClass() }}" style="font-size:12px;padding:5px 14px">{{ $thesis->status->label() }}</span>
        </div>
    </div>

    @foreach (\App\Enums\DocumentStage::cases() as $stage)
        <div class="card animate-fadeUp d1" style="margin-top:20px">
            <div class="card-header"><div class="card-title">{{ $stage->label() }}</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                @forelse ($documentsByStage->get($stage->value, collect()) as $document)
                    <div style="padding:12px 14px;border:1px solid var(--border);border-radius:12px">
                        <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:8px">{{ $document->name }}</div>
                        <div style="display:flex;flex-direction:column;gap:6px">
                            @foreach ($document->versions as $version)
                                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;font-size:12.5px;color:var(--text)">
                                    <span>
                                        Versi {{ $version->version_number }} &middot; {{ $version->original_name }} &middot; {{ $version->uploader?->name ?? '—' }} &middot; {{ $version->created_at->format('d M Y H:i') }}
                                        <span class="badge {{ $version->is_active ? 'badge-green' : 'badge-gray' }}">{{ $version->is_active ? 'Aktif' : 'Versi lama' }}</span>
                                    </span>
                                    <a href="{{ route('prodi.documents.versions.download', $version) }}" class="auth-link">Unduh</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-muted" style="font-size:12.5px">Belum ada dokumen.</p>
                @endforelse
            </div>
        </div>
    @endforeach
</x-app-layout>
