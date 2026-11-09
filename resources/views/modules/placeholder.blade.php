<x-app-layout>
    <x-slot name="header">{{ $title }}</x-slot>

    <div class="card animate-fadeUp">
        <div class="card-body" style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:14px;padding:56px 24px">
            <div class="a-bg-lt a-text" style="width:56px;height:56px;border-radius:16px;display:grid;place-items:center;font-size:22px">
                <i class="fa-solid {{ $icon }}"></i>
            </div>
            <div>
                <h2 class="font-display" style="font-size:17px;font-weight:600;color:var(--text)">{{ $title }}</h2>
                @if ($description)
                    <p class="text-muted" style="font-size:13px;margin-top:6px;max-width:440px">{{ $description }}</p>
                @endif
            </div>
            <span class="badge badge-amber">
                <i class="fa-solid fa-hammer" style="font-size:9px"></i>Modul dalam pengembangan
            </span>
        </div>
    </div>
</x-app-layout>
