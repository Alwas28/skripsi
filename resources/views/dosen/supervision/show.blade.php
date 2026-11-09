<x-app-layout>
    <x-slot name="header">Detail Bimbingan</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
            <div>
                <div style="font-size:15px;font-weight:600;color:var(--text)">{{ $session->thesis->student->name }}</div>
                <div class="text-muted" style="font-size:12.5px;margin-top:2px">{{ $session->thesis->title?->title }}</div>
                <div style="font-size:13px;color:var(--text);margin-top:8px">{{ $session->phase->supervisionLabel() }} &middot; {{ $session->chapter }} &middot; {{ $session->topic }} &middot; {{ $session->session_date->format('d M Y') }}</div>
            </div>
            <span class="badge {{ $session->status->badgeClass() }}" style="font-size:12px;padding:5px 14px">{{ $session->status->label() }}</span>
        </div>
    </div>

    <div class="two-col animate-fadeUp d1" style="margin-top:20px">
        <div class="card">
            <div class="card-header"><div class="card-title">Berkas &amp; Riwayat</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text)">
                @foreach ($session->files as $file)
                    <div>Berkas: <a href="{{ route('dosen.document-review.download', $file->documentVersion) }}" class="auth-link">{{ $file->documentVersion->original_name }}</a></div>
                @endforeach
                @foreach ($session->revisions as $revision)
                    <div>Revisi: <a href="{{ route('dosen.document-review.download', $revision->documentVersion) }}" class="auth-link">{{ $revision->documentVersion->original_name }}</a>{{ $revision->notes ? ' — '.$revision->notes : '' }}</div>
                @endforeach
                @foreach ($session->notes as $note)
                    <div style="padding:10px 12px;border:1px solid var(--border);border-radius:10px">
                        <span class="badge {{ $note->decision->badgeClass() }}">{{ $note->decision->label() }}</span>
                        <span class="text-muted" style="font-size:11px">{{ $note->user?->name }} &middot; {{ $note->created_at->format('d M Y H:i') }}</span>
                        @if ($note->note)
                            <p style="margin-top:6px;white-space:pre-line">{{ $note->note }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        @if ($session->status->awaitsLecturer() && Auth::user()->hasPermission('edit.supervision'))
            <div class="card">
                <div class="card-header"><div class="card-title">Review</div></div>
                <form method="POST" action="{{ route('dosen.supervision.review', $session) }}">
                    @csrf
                    <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                        <div>
                            <x-input-label for="decision" value="Keputusan" />
                            <select id="decision" name="decision" class="f-input" required>
                                <option value="approved">Setujui</option>
                                <option value="revision_required">Minta Revisi</option>
                                <option value="reviewed">Simpan Catatan Saja</option>
                            </select>
                            <x-input-error :messages="$errors->get('decision')" />
                        </div>
                        <div>
                            <x-input-label for="note" value="Catatan" />
                            <textarea id="note" name="note" rows="5" class="f-input">{{ old('note') }}</textarea>
                            <x-input-error :messages="$errors->get('note')" />
                        </div>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa-solid fa-check"></i>Simpan Review</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
