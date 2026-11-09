<x-app-layout>
    <x-slot name="header">Review Dokumen</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-body">
            <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $thesis->student->name }}</div>
            <div class="text-muted" style="font-size:12.5px;margin-top:2px">{{ $thesis->title?->title }}</div>
        </div>
    </div>

    @foreach (\App\Enums\DocumentStage::cases() as $stage)
        @continue(! $documentsByStage->has($stage->value))
        <div class="card animate-fadeUp d1" style="margin-top:20px">
            <div class="card-header"><div class="card-title">{{ $stage->label() }}</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                @foreach ($documentsByStage->get($stage->value) as $document)
                    <div style="padding:12px 14px;border:1px solid var(--border);border-radius:12px">
                        <div style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:8px">{{ $document->name }}</div>
                        @foreach ($document->versions as $version)
                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;font-size:12.5px;color:var(--text);margin-bottom:6px">
                                <span>
                                    Versi {{ $version->version_number }} &middot; {{ $version->original_name }} &middot; {{ $version->created_at->format('d M Y H:i') }}
                                    <span class="badge {{ $version->is_active ? 'badge-green' : 'badge-gray' }}">{{ $version->is_active ? 'Aktif' : 'Versi lama' }}</span>
                                    @if ($version->review_status)
                                        @php $review = \App\Enums\DocumentReviewStatus::from($version->review_status); @endphp
                                        <span class="badge {{ $review->badgeClass() }}">{{ $review->label() }}</span>
                                    @endif
                                </span>
                                <a href="{{ route('dosen.document-review.download', $version) }}" class="auth-link">Unduh</a>
                            </div>
                            @if ($version->review_notes)
                                <p class="text-muted" style="font-size:12px;white-space:pre-line;margin-bottom:6px">{{ $version->review_notes }}</p>
                            @endif
                            @if ($version->is_active && Auth::user()->hasPermission('edit.supervision'))
                                <form method="POST" action="{{ route('dosen.document-review.review', $version) }}" style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0">
                                    @csrf
                                    <select name="review_status" class="f-input" style="max-width:190px" required aria-label="Hasil review {{ $document->name }}">
                                        @foreach (\App\Enums\DocumentReviewStatus::cases() as $status)
                                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                    <input name="review_notes" type="text" class="f-input" style="flex:1;min-width:200px" placeholder="Catatan (wajib jika perlu perbaikan)" aria-label="Catatan review {{ $document->name }}">
                                    <button type="submit" class="btn btn-secondary">Simpan</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</x-app-layout>
