<x-app-layout>
    <x-slot name="header">Notifikasi</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-bell a-text" style="margin-right:8px"></i>Notifikasi Anda</div>
            @if (Auth::user()->unreadNotifications()->exists())
                <form method="POST" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary" style="padding:6px 12px;font-size:12px">Tandai semua dibaca</button>
                </form>
            @endif
        </div>
        <div>
            @forelse ($notifications as $notification)
                <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                    @csrf
                    <button type="submit" style="display:flex;gap:12px;width:100%;text-align:left;padding:14px 20px;border:none;border-bottom:1px solid var(--border);background:{{ $notification->read_at ? 'transparent' : 'var(--surface2)' }};cursor:pointer">
                        <i class="fa-solid {{ $notification->read_at ? 'fa-envelope-open' : 'fa-envelope' }} a-text" style="margin-top:3px"></i>
                        <span style="flex:1">
                            <span style="display:block;font-size:13.5px;color:var(--text);font-weight:{{ $notification->read_at ? 500 : 700 }}">{{ $notification->data['title'] ?? 'Notifikasi' }}</span>
                            <span style="display:block;font-size:12.5px;color:var(--sub);margin-top:2px">{{ $notification->data['message'] ?? '' }}</span>
                            <span style="display:block;font-size:11px;color:var(--muted);margin-top:4px">{{ $notification->created_at->format('d M Y H:i') }}</span>
                        </span>
                    </button>
                </form>
            @empty
                <p class="text-muted" style="text-align:center;padding:32px;font-size:13px">Belum ada notifikasi.</p>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="card-body" style="border-top:1px solid var(--border)">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
