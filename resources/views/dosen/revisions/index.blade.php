<x-app-layout>
    <x-slot name="header">Revisi</x-slot>

    @forelse ($revisions as $revision)
        <div class="card animate-fadeUp" style="margin-bottom:20px">
            <div class="card-header">
                <div class="card-title">{{ $revision->attempt->exam->thesis->student->name }} &middot; {{ $revision->attempt->kind()->label() }} &middot; {{ $revision->chapter }}</div>
                <span class="badge {{ $revision->status->badgeClass() }}">{{ $revision->status->label() }}</span>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text)">
                <p style="white-space:pre-line">{{ $revision->notes }}</p>
                @if ($revision->documentVersion)
                    <div>Berkas: <a href="{{ route('dosen.revisions.download', $revision) }}" class="auth-link">{{ $revision->documentVersion->original_name }}</a></div>
                @endif
                @if ($revision->status === \App\Enums\ExamRevisionStatus::Submitted && Auth::user()->hasPermission('edit.revision'))
                    <form method="POST" action="{{ route('dosen.revisions.validate', $revision) }}" style="display:flex;gap:8px;flex-wrap:wrap">
                        @csrf
                        <select name="decision" class="f-input" style="max-width:170px" required aria-label="Keputusan revisi {{ $revision->chapter }}">
                            <option value="approved">Setujui</option>
                            <option value="revision">Minta Perbaikan</option>
                        </select>
                        <input name="notes" type="text" class="f-input" style="flex:1;min-width:200px" placeholder="Catatan (wajib jika minta perbaikan)" aria-label="Catatan revisi {{ $revision->chapter }}">
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                    <x-input-error :messages="$errors->get('notes')" />
                @endif
            </div>
        </div>
    @empty
        <div class="card animate-fadeUp">
            <div class="card-body"><p class="text-muted" style="font-size:13px">Tidak ada item revisi.</p></div>
        </div>
    @endforelse

    @if ($revisions->hasPages())
        <div style="margin-top:20px">{{ $revisions->links() }}</div>
    @endif
</x-app-layout>
