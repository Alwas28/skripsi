<x-app-layout>
    <x-slot name="header">Pembimbing</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header"><div class="card-title">Dosen Pembimbing</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px">
            @if (! $isLinked)
                <p class="text-muted" style="font-size:13px">Akun Anda belum dihubungkan ke data mahasiswa. Hubungi Admin Prodi.</p>
            @else
                @forelse ($supervisors as $supervisor)
                    <div style="padding:12px 14px;border:1px solid var(--border);border-radius:12px">
                        <div class="text-muted" style="font-size:11px;text-transform:uppercase;letter-spacing:.5px">Pembimbing {{ $supervisor->position }}</div>
                        <div style="font-size:14px;color:var(--text);margin-top:3px;font-weight:600">{{ $supervisor->lecturer->full_name }}</div>
                        <div class="text-muted" style="font-size:12.5px;margin-top:2px">{{ $supervisor->lecturer->expertise_field ?: 'Bidang keahlian belum diisi' }}</div>
                    </div>
                @empty
                    <p class="text-muted" style="font-size:13px">Pembimbing belum ditetapkan. Pembimbing akan tampil di sini setelah disetujui Kaprodi.</p>
                @endforelse
            @endif
        </div>
    </div>
</x-app-layout>
