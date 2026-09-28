@props(['title' => null])

@php
    $user = auth('web')->user();
@endphp

<!DOCTYPE html>
@php
    // The operator panel has no per-shop accent, but it uses the same button
    // components, so it must define the same token.
    $accent = App\Models\Tenant::ACCENT_PALETTE[0];
    $onAccent = App\Support\Contrast::onColor($accent);
@endphp

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="dark"
      style="--color-accent: {{ $accent }}; --color-on-accent: {{ $onAccent }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#06080d">

    {{-- The operator panel is not public, so these are for a pasted link in a
         team chat rather than for search engines. --}}
    <meta property="og:title" content="{{ config('app.name') }}">
    <meta property="og:image" content="{{ asset('og-image.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    {{-- Applies a per-user theme override before first paint. Inline and
         blocking on purpose: from the bundle it would run after the page has
         already painted, and a till that flashes dark then goes light is
         worse than no toggle. --}}
    <script>
        try {
            var t = localStorage.getItem('lorapok.theme');
            if (t === 'light' || t === 'dark') {
                document.documentElement.setAttribute('data-theme', t);
            }
        } catch (e) { /* storage blocked; the shop default stands */ }
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell-bg min-h-screen antialiased">
    <a href="#main-content" class="skip-link">Skip to content</a>

    @if ($user)
        <x-app-shell
            :brand-name="config('app.name')"
            brand-sub="Operator panel"
            :brand-href="route('central.shops')"
            :logout-route="route('central.logout')"
            :user-name="$user->name"
            :user-meta="$user->email">

            <x-slot:nav>
                <x-central.nav />
            </x-slot:nav>

            {{ $slot }}
        </x-app-shell>
    @else
        <main id="main-content">{{ $slot }}</main>
    @endif
    <x-ui.help-drawer />
</body>
</html>
