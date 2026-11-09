<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    @include('layouts.partials.theme-head')
</head>
<body class="antialiased">

<div id="toast-container"></div>

<div class="auth-shell">
    <div class="auth-card animate-fadeUp">
        <a href="{{ route('login') }}" class="auth-logo">
            <div class="a-grad" style="width:52px;height:52px;border-radius:15px;display:grid;place-items:center">
                <i class="fa-solid fa-graduation-cap" style="color:#fff;font-size:22px"></i>
            </div>
            <div class="font-display auth-title" style="font-weight:700;color:var(--text)">
                {{ config('app.name', 'Laravel') }}
            </div>
        </a>

        {{ $slot }}
    </div>
</div>

</body>
</html>
