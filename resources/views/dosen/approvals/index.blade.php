<x-app-layout>
    <x-slot name="header">Persetujuan</x-slot>

    @forelse ($items as $item)
        <div class="card animate-fadeUp" style="margin-bottom:20px">
            <div class="card-header">
                <div class="card-title">{{ $item['thesis']->student->name }} &middot; {{ $item['kind']->label() }}</div>
                @if ($item['approved'])
                    <span class="badge badge-green">Sudah Anda setujui</span>
                @endif
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;font-size:13px;color:var(--text)">
                <div class="text-muted" style="font-size:12.5px">{{ $item['thesis']->title?->title }}</div>
                @foreach ($item['checks'] as $check)
                    <div><i class="fa-solid {{ $check['met'] ? 'fa-circle-check' : 'fa-circle-xmark' }}" style="color:{{ $check['met'] ? '#34d399' : '#f87171' }};margin-right:6px"></i>{{ $check['label'] }}</div>
                @endforeach
                @if (! $item['approved'] && Auth::user()->hasPermission('edit.approval'))
                    <form method="POST" action="{{ route('dosen.approvals.approve', [$item['thesis'], $item['kind']]) }}" onsubmit="return confirm('Setujui mahasiswa ini untuk {{ $item['kind']->label() }}?')">
                        @csrf
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i>Setujui Kelayakan Ujian</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <div class="card animate-fadeUp">
            <div class="card-body"><p class="text-muted" style="font-size:13px">Belum ada mahasiswa yang menunggu persetujuan kelayakan ujian.</p></div>
        </div>
    @endforelse
</x-app-layout>
