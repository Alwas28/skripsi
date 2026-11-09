<x-app-layout>
    <x-slot name="header">Dokumen</x-slot>

    @if (! $thesis)
        <div class="card animate-fadeUp">
            <div class="card-body">
                <p class="text-muted" style="font-size:13px">Belum ada data skripsi. Mulai dari menu Pengajuan Judul.</p>
            </div>
        </div>
    @else
        @if ($openStages && Auth::user()->hasPermission('tambah.document'))
            <div class="card animate-fadeUp">
                <div class="card-header"><div class="card-title">Unggah Dokumen</div></div>
                <form method="POST" action="{{ route('mahasiswa.documents.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="card-body" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:start">
                        <div>
                            <x-input-label for="stage" value="Tahapan" />
                            <select id="stage" name="stage" class="f-input" required>
                                @foreach ($openStages as $stage)
                                    <option value="{{ $stage->value }}" @selected(old('stage') === $stage->value)>{{ $stage->label() }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('stage')" />
                        </div>
                        <div>
                            <x-input-label for="name" value="Nama Dokumen" />
                            <x-text-input id="name" name="name" type="text" :value="old('name')" required placeholder="mis. Proposal Bab I-III" />
                            <x-input-error :messages="$errors->get('name')" />
                        </div>
                        <div>
                            <x-input-label for="file" value="Berkas (PDF/DOC, maks 10MB)" />
                            <input id="file" name="file" type="file" class="f-input" accept=".pdf,.doc,.docx" required>
                            <x-input-error :messages="$errors->get('file')" />
                        </div>
                    </div>
                    <div class="card-body" style="border-top:1px solid var(--border)">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-upload"></i>Unggah</button>
                    </div>
                </form>
            </div>
        @endif

        @foreach (\App\Enums\DocumentStage::cases() as $stage)
            <div class="card animate-fadeUp d1" style="margin-top:20px">
                <div class="card-header">
                    <div class="card-title">{{ $stage->label() }}</div>
                    @unless ($stage->isOpenFor($thesis->status))
                        <span class="badge badge-gray">Unggah ditutup</span>
                    @endunless
                </div>
                <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
                    @forelse ($documentsByStage->get($stage->value, collect()) as $document)
                        @php $activeVersion = $document->versions->firstWhere('is_active', true); @endphp
                        <div style="padding:12px 14px;border:1px solid var(--border);border-radius:12px">
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                                <div>
                                    <div style="font-size:14px;font-weight:600;color:var(--text)">{{ $document->name }}</div>
                                    @if ($activeVersion)
                                        <div class="text-muted" style="font-size:12px;margin-top:2px">
                                            Versi {{ $activeVersion->version_number }} &middot; {{ $activeVersion->uploader?->name ?? '—' }} &middot; {{ $activeVersion->created_at->format('d M Y H:i') }}
                                        </div>
                                    @endif
                                </div>
                                @if ($activeVersion)
                                    <a href="{{ route('mahasiswa.documents.versions.download', $activeVersion) }}" class="btn btn-secondary" style="padding:6px 12px">
                                        <i class="fa-solid fa-download" style="font-size:11px"></i>Unduh
                                    </a>
                                @endif
                            </div>

                            @if ($stage->isOpenFor($thesis->status) && Auth::user()->hasPermission('tambah.document'))
                                <form method="POST" action="{{ route('mahasiswa.documents.versions.store', $document) }}" enctype="multipart/form-data" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:12px">
                                    @csrf
                                    <input name="file" type="file" class="f-input" style="flex:1;min-width:200px" accept=".pdf,.doc,.docx" required aria-label="Berkas versi baru {{ $document->name }}">
                                    <button type="submit" class="btn btn-secondary"><i class="fa-solid fa-upload"></i>Unggah Versi Baru</button>
                                </form>
                            @endif

                            @if ($document->versions->count() > 1)
                                <details style="margin-top:12px">
                                    <summary class="text-muted" style="font-size:12px;cursor:pointer">Riwayat versi ({{ $document->versions->count() }})</summary>
                                    <div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">
                                        @foreach ($document->versions as $version)
                                            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;font-size:12.5px;color:var(--text)">
                                                <span>
                                                    Versi {{ $version->version_number }} &middot; {{ $version->original_name }} &middot; {{ $version->uploader?->name ?? '—' }} &middot; {{ $version->created_at->format('d M Y H:i') }}
                                                    <span class="badge {{ $version->is_active ? 'badge-green' : 'badge-gray' }}">{{ $version->is_active ? 'Aktif' : 'Versi lama' }}</span>
                                                </span>
                                                <a href="{{ route('mahasiswa.documents.versions.download', $version) }}" class="auth-link">Unduh</a>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted" style="font-size:12.5px">Belum ada dokumen.</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    @endif
</x-app-layout>
