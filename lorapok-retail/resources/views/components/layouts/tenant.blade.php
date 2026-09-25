@props(['title' => null])

@php
    // The tenant's accent is interpolated into CSS, so it is validated here as
    // well as on write. A stored value that is not a plain hex colour is
    // dropped rather than rendered.
    $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) tenant('accent'))
        ? tenant('accent')
        : '#7c5cff';
    $theme = in_array(tenant('theme'), ['dark', 'light'], true) ? tenant('theme') : 'dark';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $theme }}"
      style="--color-accent: {{ $accent }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ tenant('name') ?? config('app.name') }}</title>

    {{-- Served by Laravel so the installed app carries this shop's name. --}}
    <link rel="manifest" href="{{ route('tenant.manifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ route('tenant.icon', ['size' => 192]) }}">
    <link rel="apple-touch-icon" href="{{ route('tenant.icon', ['size' => 192]) }}">
    <meta name="theme-color" content="#06080d">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell-bg min-h-screen antialiased">
    {{-- First focusable element on the page. --}}
    <a href="#main-content" class="skip-link">Skip to content</a>

    {{-- Shown only when the browser reports no connection. A cashier needs
         to know immediately that a sale cannot be completed, rather than
         discovering it when checkout fails. --}}
    <div data-offline-banner hidden
         class="fixed inset-x-0 top-0 z-50 bg-[var(--color-warn)] px-4 py-2 text-center text-sm font-medium text-black">
        Offline — you can look things up, but sales cannot be completed.
    </div>

    <main id="main-content">
        {{ $slot }}
    </main>
</body>
</html>
