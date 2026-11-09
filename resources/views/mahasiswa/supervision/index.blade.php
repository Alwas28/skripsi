<x-app-layout>
    <x-slot name="header">Bimbingan</x-slot>

    @if (! $thesis || $supervisors->isEmpty())
        <div class="card animate-fadeUp">
            <div class="card-body">
                <p class="text-muted" style="font-size:13px">Bimbingan bisa dimulai setelah pembimbing Anda ditetapkan.</p>
            </div>
        </div>
    @else
        @if ($kind && Auth::user()->hasPermission('tambah.supervision'))
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Ajukan {{ $kind->supervisionLabel() }}</div></div>
                <form method="POST" action="{{ route('mahasiswa.supervision.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;align-items:start">
                        <div>
                            <x-input-label for="lecturer_id" value="Pembimbing" />
                            <select id="lecturer_id" name="lecturer_id" class="f-input" required>
                                @foreach ($supervisors as $supervisor)
                                    <option value="{{ $supervisor->lecturer_id }}" @selected((int) old('lecturer_id') === $supervisor->lecturer_id)>Pembimbing {{ $supervisor->position }} — {{ $supervisor->lecturer->full_name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('lecturer_id')" />
                        </div>
                        <div>
                            <x-input-label for="chapter" value="Bab" />
                            <x-text-input id="chapter" name="chapter" type="text" :value="old('chapter')" required placeholder="mis. Bab II" />
                            <x-input-error :messages="$errors->get('chapter')" />
                        </div>
                        <div>
                            <x-input-label for="topic" value="Topik" />
                            <x-text-input id="topic" name="topic" type="text" :value="old('topic')" required />
                            <x-input-error :messages="$errors->get('topic')" />
                        </div>
                        <div>
                            <x-input-label for="session_date" value="Tanggal" />
                            <x-text-input id="session_date" name="session_date" type="date" :value="old('session_date', now()->toDateString())" required />
                            <x-input-error :messages="$errors->get('session_date')" />
                        </div>
                        <div>
                            <x-input-label for="file" value="Berkas (PDF/DOC, maks 10MB)" />
                            <input id="file" name="file" type="file" class="f-input" accept=".pdf,.doc,.docx" required>
                            <x-input-error :messages="$errors->get('file')" />
                        </div>
                    </div>
                    <div class="card-body" style="border-top:1px solid var(--border)">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i>Ajukan Bimbingan</button>
                    </div>
                </form>
            </div>
        @endif

        @forelse ($sessions as $session)
            <div class="card animate-fadeUp d1" style="margin-top:20px">
                <div class="card-header">
                    <div class="card-title">#{{ $session->id }} &middot; {{ $session->chapter }} &middot; {{ $session->topic }}</div>
                    <span class="badge {{ $session->status->badgeClass() }}">{{ $session->status->label() }}</span>
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text)">
                    <div class="text-muted" style="font-size:12px">{{ $session->phase->supervisionLabel() }} &middot; {{ $session->lecturer->full_name }} &middot; {{ $session->session_date->format('d M Y') }}</div>

                    @foreach ($session->files as $file)
                        <div>Berkas: <a href="{{ route('mahasiswa.documents.versions.download', $file->documentVersion) }}" class="auth-link">{{ $file->documentVersion->original_name }}</a></div>
                    @endforeach
                    @foreach ($session->revisions as $revision)
                        <div>Revisi: <a href="{{ route('mahasiswa.documents.versions.download', $revision->documentVersion) }}" class="auth-link">{{ $revision->documentVersion->original_name }}</a>{{ $revision->notes ? ' — '.$revision->notes : '' }}</div>
                    @endforeach
                    @foreach ($session->notes as $note)
                        <div style="padding:10px 12px;border:1px solid var(--border);border-radius:10px">
                            <span class="badge {{ $note->decision->badgeClass() }}">{{ $note->decision->label() }}</span>
                            <span class="text-muted" style="font-size:11px">{{ $note->created_at->format('d M Y H:i') }}</span>
                            @if ($note->note)
                                <p style="margin-top:6px;white-space:pre-line">{{ $note->note }}</p>
                            @endif
                        </div>
                    @endforeach

                    @if ($session->status === \App\Enums\SupervisionStatus::RevisionRequired && Auth::user()->hasPermission('tambah.supervision'))
                        <form method="POST" action="{{ route('mahasiswa.supervision.revise', $session) }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:8px">
                            @csrf
                            <input name="file" type="file" class="f-input" accept=".pdf,.doc,.docx" required aria-label="Berkas revisi">
                            <textarea name="notes" rows="2" class="f-input" placeholder="Catatan untuk pembimbing (opsional)" aria-label="Catatan revisi"></textarea>
                            <button type="submit" class="btn btn-primary" style="align-self:flex-start"><i class="fa-solid fa-upload"></i>Kirim Revisi</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="card animate-fadeUp d1" style="margin-top:20px">
                <div class="card-body"><p class="text-muted" style="font-size:13px">Belum ada sesi bimbingan.</p></div>
            </div>
        @endforelse
    @endif
</x-app-layout>
