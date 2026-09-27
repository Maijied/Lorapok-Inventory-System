@props(['seo'])

{{--
    The public site.

    Server-rendered Blade, not Livewire: a crawler and a first-time visitor on
    a slow connection in Dhaka both get HTML with no JavaScript required to
    read it. Livewire earns its place inside the application, where state
    changes on every interaction; on a landing page it is pure cost.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <x-seo :seo="$seo" />

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#06080d">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="app-shell-bg min-h-screen antialiased">
    <a href="#main-content" class="skip-link">Skip to content</a>

    <header class="border-b border-[var(--color-border)]">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('marketing.home') }}" class="flex items-center gap-2.5">
                <img src="{{ asset('favicon-32x32.png') }}" alt="" width="28" height="28" class="rounded-md">
                <span class="text-sm font-semibold text-[var(--color-text)]">{{ config('app.name') }}</span>
            </a>

            <nav aria-label="Main" class="flex items-center gap-1 text-sm">
                {{-- The section links are hidden on a phone rather than
                     collapsed into a menu: there are three of them, they are
                     all reachable from the page body, and a hamburger that
                     hides three links costs more than it saves. The one thing
                     someone came to do stays visible. --}}
                @foreach ([
                    ['marketing.features', 'Features'],
                    ['marketing.pricing', 'Pricing'],
                    ['marketing.contact', 'Contact'],
                ] as [$route, $label])
                    <a href="{{ route($route) }}"
                       @if (request()->routeIs($route)) aria-current="page" @endif
                       class="hidden rounded-[var(--radius)] px-3 py-2 transition-colors sm:block
                              {{ request()->routeIs($route)
                                  ? 'text-[var(--color-text)]'
                                  : 'text-[var(--color-muted)] hover:text-[var(--color-text)]' }}">
                        {{ $label }}
                    </a>
                @endforeach

                <x-ui.button size="sm" :href="route('marketing.apply')" class="ml-2">Start a shop</x-ui.button>
            </nav>
        </div>
    </header>

    <main id="main-content">
        {{ $slot }}
    </main>

    <footer class="mt-24 border-t border-[var(--color-border)]">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-4 py-8 text-sm sm:px-6 lg:px-8">
            <p class="text-[var(--color-muted)]">
                &copy; {{ date('Y') }} Lorapok Labs. Built in Dhaka.
            </p>

            <nav aria-label="Legal" class="flex gap-4">
                <a href="{{ route('marketing.privacy') }}" class="text-[var(--color-muted)] hover:text-[var(--color-text)]">Privacy</a>
                <a href="{{ route('marketing.terms') }}" class="text-[var(--color-muted)] hover:text-[var(--color-text)]">Terms</a>
                <a href="{{ route('central.login') }}" class="text-[var(--color-muted)] hover:text-[var(--color-text)]">Operator</a>
            </nav>
        </div>
    </footer>
</body>
</html>
