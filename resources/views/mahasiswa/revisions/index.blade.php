<x-app-layout>
    <x-slot name="header">Revisi</x-slot>

    @forelse ($revisions as $revision)
        <div class="card animate-fadeUp" style="margin-bottom:20px">
            <div class="card-header">
                <div class="card-title">{{ $revision->attempt->kind()->label() }} &middot; {{ $revision->chapter }}</div>
                <span class="badge {{ $revision->status->badgeClass() }}">{{ $revision->status->label() }}</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text)">
                <div class="text-muted" style="font-size:12px">Penguji: {{ $revision->lecturer->full_name }}</div>
                <p style="white-space:pre-line">{{ $revision->notes }}</p>

                @if ($revision->documentVersion)
                    <div>Berkas: <a href="{{ route('mahasiswa.documents.versions.download', $revision->documentVersion) }}" class="auth-link">{{ $revision->documentVersion->original_name }}</a></div>
                @endif

                @foreach ($revision->validations as $validation)
                    @if ($validation->notes)
                        <div style="padding:10px 12px;border:1px solid var(--border);border-radius:10px">Catatan penguji: {{ $validation->notes }}</div>
                    @endif
                @endforeach

                @if ($revision->status === \App\Enums\ExamRevisionStatus::Open && Auth::user()->hasPermission('tambah.revision'))
                    <form method="POST" action="{{ route('mahasiswa.revisions.submit', $revision) }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap">
                        @csrf
                        <input name="file" type="file" class="f-input" style="flex:1;min-width:200px" accept=".pdf,.doc,.docx" required aria-label="Berkas revisi {{ $revision->chapter }}">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i>Kirim Revisi</button>
                    </form>
                    <x-input-error :messages="$errors->get('file')" />
                @endif
            </div>
        </div>
    @empty
        <div class="card animate-fadeUp">
            <div class="card-body"><p class="text-muted" style="font-size:13px">Tidak ada item revisi.</p></div>
        </div>
    @endforelse
</x-app-layout>
