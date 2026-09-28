@props(['title' => null])

@php
    // The tenant's accent is interpolated into CSS, so it is validated here as
    // well as on write. A stored value that is not a plain hex colour is
    // dropped rather than rendered.
    $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) tenant('accent'))
        ? tenant('accent')
        : App\Models\Tenant::ACCENT_PALETTE[0];

    // Which text colour stays legible on this shop's accent is a per-shop
    // question, and CSS has no luminance function — so it is answered here and
    // injected. Hardcoding `text-white` on accent buttons put nine primary
    // actions below the AA floor.
    $onAccent = App\Support\Contrast::onColor($accent);
    $theme = in_array(tenant('theme'), ['dark', 'light'], true) ? tenant('theme') : 'dark';

    $user = auth('tenant')->user();
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-theme="{{ $theme }}"
      style="--color-accent: {{ $accent }}; --color-on-accent: {{ $onAccent }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' — ' : '' }}{{ tenant('name') ?? config('app.name') }}</title>

    {{-- Served by Laravel so the installed app carries this shop's name. --}}
    <link rel="manifest" href="{{ route('tenant.manifest') }}">
    <link rel="icon" type="image/svg+xml" href="{{ route('tenant.icon', ['size' => 192]) }}">
    <link rel="apple-touch-icon" href="{{ route('tenant.icon', ['size' => 192]) }}">
    <meta name="theme-color" content="#06080d">

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
    {{-- First focusable element on the page. --}}
    <a href="#main-content" class="skip-link">Skip to content</a>

    {{-- Shown only when the browser reports no connection. A cashier needs
         to know immediately that a sale cannot be completed, rather than
         discovering it when checkout fails. --}}
    <div data-offline-banner hidden
         class="fixed inset-x-0 top-0 z-50 bg-[var(--color-warn)] px-4 py-2 text-center text-sm font-medium text-black">
        Offline — you can look things up, but sales cannot be completed.
    </div>

    @php
        $impersonation = app(App\Domain\Impersonation\ImpersonationService::class);
    @endphp

    @if ($impersonation->active())
        {{-- Permanent and unmissable, for the whole session. An operator
             acting inside somebody else's business without the shop being
             able to see it is the thing this entire mechanism exists to
             prevent. Rendered above the shell so it cannot be scrolled past. --}}
        <div role="status"
             class="sticky top-0 z-[60] flex flex-wrap items-center justify-center gap-x-3 gap-y-1 border-b border-[var(--r-attention)] bg-[var(--r-attention)] px-4 py-2 text-center text-sm font-medium text-black">
            <span>
                Lorapok support is signed in to your shop
                ({{ $impersonation->canWrite() ? 'can make changes' : 'read-only' }}).
            </span>
            <span class="opacity-80">Reason: {{ $impersonation->context()['reason'] ?? 'not given' }}</span>
            <form method="POST" action="{{ route('tenant.logout') }}">
                @csrf
                <button type="submit" class="underline underline-offset-2">End session</button>
            </form>
        </div>
    @endif

    @if ($user)
        <x-app-shell
            :brand-name="tenant('name') ?? config('app.name')"
            brand-sub="Lorapok Retail"
            :brand-href="route('tenant.dashboard')"
            :logout-route="route('tenant.logout')"
            :user-name="$user->name"
            :user-meta="$user->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->join(', ') ?: null">

            <x-slot:nav>
                <x-tenant.nav />
            </x-slot:nav>

            {{ $slot }}
        </x-app-shell>
    @else
        {{-- Sign-in and the public manifest routes get no chrome. --}}
        <main id="main-content">
            {{ $slot }}
        </main>
    @endif
</body>
</html>
