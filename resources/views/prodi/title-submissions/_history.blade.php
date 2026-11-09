<div class="card">
    <div class="card-header">
        <div class="card-title"><i class="fa-solid fa-clock-rotate-left a-text" style="margin-right:8px"></i>Riwayat Status</div>
    </div>
    <div style="padding:8px 0">
        @forelse ($thesis->statusHistories as $history)
            <div style="padding:10px 20px;border-bottom:1px solid var(--border)">
                <div style="font-size:12.5px;color:var(--text)">
                    {{ $history->from_status ? \App\Enums\ThesisStatus::from($history->from_status)->label() : 'Dibuat' }}
                    <i class="fa-solid fa-arrow-right" style="font-size:9px;color:var(--muted);margin:0 4px"></i>
                    {{ \App\Enums\ThesisStatus::from($history->to_status)->label() }}
                </div>
                <div class="text-muted" style="font-size:11px;margin-top:2px">
                    {{ $history->changedBy?->name ?? 'Sistem' }} &middot; {{ $history->created_at->format('d M Y H:i') }}
                </div>
            </div>
        @empty
            <p class="text-muted" style="font-size:12.5px;padding:10px 20px">Belum ada riwayat.</p>
        @endforelse
    </div>
</div>
