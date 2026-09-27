@props(['title' => null])

@php
    $user = auth('web')->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
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
</body>
</html>
