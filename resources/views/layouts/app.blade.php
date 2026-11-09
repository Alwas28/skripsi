<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ isset($header) ? $header . ' — ' : '' }}{{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.theme-head')
</head>
<body class="antialiased">

<div id="toast-container"></div>

<!-- Mobile overlay -->
<div id="ov"></div>

<!-- ══════════════════ SIDEBAR ══════════════════ -->
<aside id="sb">

    <!-- Logo -->
    <div style="display:flex;align-items:center;gap:12px;padding:18px 20px 18px;border-bottom:1px solid var(--border);flex-shrink:0">
        <a href="{{ route('dashboard') }}" class="a-grad" style="width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex-shrink:0;text-decoration:none">
            <i class="fa-solid fa-graduation-cap" style="color:#fff;font-size:16px"></i>
        </a>
        <div>
            <div class="font-display" style="font-size:16px;font-weight:700;color:var(--text);line-height:1.1">
                {{ config('app.name', 'Laravel') }}
            </div>
            <div style="font-size:9px;color:var(--muted);letter-spacing:.3px;margin-top:1px">Layanan Skripsi</div>
        </div>
    </div>

    <!-- Nav -->
    @php
        // Every role's menu group is combined. A user with no menu group at
        // all gets the minimal fallback — see App\Models\User::menuGroups().
        $sbGroups = Auth::user()->menuGroups();
        $sbSeenRoutes = [];
        $sbSections = collect($sbGroups)
            ->flatMap(fn (string $group) => collect(config("menu.{$group}.sections"))->map(fn (array $section) => [
                ...$section,
                'label' => count($sbGroups) > 1 && ! in_array($section['label'], ['Utama', 'Akun'], true)
                    ? ucfirst($group).' · '.$section['label']
                    : $section['label'],
            ]))
            ->whenEmpty(fn () => collect([
                ['label' => 'Utama', 'items' => [
                    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'fa-chart-pie', 'route' => 'dashboard'],
                ]],
                ['label' => 'Akun', 'items' => [
                    ['key' => 'profile', 'label' => 'Profil', 'icon' => 'fa-user-gear', 'route' => 'profile.edit'],
                ]],
            ]))
            // "permission" (e.g. 'lihat.user') hides an item outright unless
            // one of the user's roles has it; a route shared by two groups
            // (Dashboard, Profil) is listed once.
            ->map(function (array $section) use (&$sbSeenRoutes) {
                $section['items'] = array_values(array_filter($section['items'], function (array $item) use (&$sbSeenRoutes) {
                    if (isset($item['permission']) && ! Auth::user()->hasPermission($item['permission'])) {
                        return false;
                    }

                    if (isset($sbSeenRoutes[$item['route']])) {
                        return false;
                    }

                    return $sbSeenRoutes[$item['route']] = true;
                }));

                return $section;
            })
            ->filter(fn (array $section) => count($section['items']) > 0)
            ->sortBy(fn (array $section) => $section['label'] === 'Akun' ? 1 : 0)
            ->values();
    @endphp
    <div id="sb-nav" style="padding:10px 10px">
        @foreach ($sbSections as $i => $section)
            <p class="nav-section" @if ($i === 0) style="margin-top:4px" @endif>{{ $section['label'] }}</p>
            @foreach ($section['items'] as $item)
                <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs($item['route']) ? 'nav-active' : 'nav-inactive' }}">
                    <i class="fa-solid {{ $item['icon'] }}" style="width:15px;text-align:center;font-size:13px"></i>{{ $item['label'] }}
                </a>
            @endforeach
        @endforeach
    </div>

    <!-- User card -->
    <div style="padding:12px 14px;border-top:1px solid var(--border);flex-shrink:0">
        <div style="display:flex;align-items:center;gap:10px;padding:10px;border-radius:12px;background:var(--surface2)">
            <a href="{{ route('profile.edit') }}" style="display:flex;align-items:center;gap:10px;flex:1;min-width:0;text-decoration:none">
                <div class="a-grad" style="width:34px;height:34px;border-radius:9px;display:grid;place-items:center;flex-shrink:0;font-weight:700;color:#fff;font-size:13px">
                    {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:600;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ Auth::user()->name }}</div>
                    <div style="font-size:11px;color:var(--muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ Auth::user()->email }}</div>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar"
                    style="width:30px;height:30px;border-radius:8px;border:1px solid var(--border);background:var(--surface);cursor:pointer;color:var(--sub);font-size:13px;transition:color .15s"
                    onmouseover="this.style.color='#f87171'" onmouseout="this.style.color='var(--sub)'">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i>
                </button>
            </form>
        </div>
    </div>
</aside>

<!-- ══════════════════ MAIN ══════════════════ -->
<div id="main">

    <!-- Header -->
    <header id="header">
        <div style="display:flex;align-items:center;gap:14px">
            <button id="hamburger" style="display:none;background:none;border:none;cursor:pointer;color:var(--sub);font-size:18px">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div>
                <div style="font-size:11px;color:var(--muted);margin-bottom:1px">Selamat datang kembali 👋</div>
                <h1 class="font-display" style="font-size:18px;font-weight:700;color:var(--text)">
                    {{ $header ?? 'Dashboard' }}
                </h1>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
            @if (Auth::user()->hasPermission('lihat.notification'))
                @php $unreadNotificationCount = Auth::user()->unreadNotifications()->count(); @endphp
                <!-- Notifications -->
                <a href="{{ route('notifications.index') }}" data-unread="{{ $unreadNotificationCount }}" aria-label="Notifikasi, {{ $unreadNotificationCount }} belum dibaca" title="Notifikasi"
                    style="position:relative;width:38px;height:38px;border-radius:10px;border:1px solid var(--border);background:var(--surface2);color:var(--sub);font-size:14px;display:grid;place-items:center;text-decoration:none">
                    <i class="fa-solid fa-bell"></i>
                    @if ($unreadNotificationCount > 0)
                        <span style="position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#ef4444;color:#fff;font-size:10px;font-weight:700;display:grid;place-items:center">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                    @endif
                </a>
            @endif
            <!-- Theme toggle -->
            <div style="position:relative">
                <button id="theme-btn"
                    style="width:38px;height:38px;border-radius:10px;border:1px solid var(--border);background:var(--surface2);cursor:pointer;color:var(--sub);font-size:14px;transition:color .15s"
                    onmouseover="this.style.color='var(--text)'" onmouseout="this.style.color='var(--sub)'">
                    <i class="fa-solid fa-circle-half-stroke"></i>
                </button>
                <div id="tp" class="hidden">
                    <button class="tp-btn active" id="tp-dark">
                        <i class="fa-solid fa-moon" style="width:14px;text-align:center"></i>Mode Gelap
                    </button>
                    <button class="tp-btn" id="tp-light">
                        <i class="fa-solid fa-sun" style="width:14px;text-align:center"></i>Mode Terang
                    </button>
                    <div class="tp-divider"></div>
                    <div class="tp-section-label">Warna Aksen</div>
                    <div class="color-swatches" id="color-swatches"></div>
                </div>
            </div>
        </div>
    </header>

    <!-- Content -->
    <div id="content">
        @if (session('status'))
            <div class="card animate-fadeUp" style="border-color:rgba(16,185,129,.35)">
                <div class="card-body" style="display:flex;align-items:center;gap:10px;padding:14px 18px">
                    <i class="fa-solid fa-circle-check" style="color:#34d399"></i>
                    <span style="font-size:13px;color:var(--text)">{{ session('status') }}</span>
                </div>
            </div>
        @endif
        @if (session('error'))
            <div class="card animate-fadeUp" style="border-color:rgba(239,68,68,.35)">
                <div class="card-body" style="display:flex;align-items:center;gap:10px;padding:14px 18px">
                    <i class="fa-solid fa-circle-exclamation" style="color:#f87171"></i>
                    <span style="font-size:13px;color:var(--text)">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>

</body>
</html>
