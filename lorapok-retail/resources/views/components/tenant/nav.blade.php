@php
    use App\Enums\Permission;

    // Explicit guard, matching ⚡reports.blade.php. The `auth:tenant`
    // middleware does make `tenant` the default guard, but relying on that
    // silently breaks any page reached outside it.
    $user = auth('tenant')->user();

    // Only routes that exist are listed. The previous welcome page linked to
    // routes that had never been defined and threw on render; a nav item for
    // an unbuilt screen is the same bug with a nicer name.
    $items = [
        [
            'route' => 'tenant.dashboard',
            'label' => 'Overview',
            'can' => Permission::VIEW_DASHBOARD,
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M3 10.5 10 4l7 6.5M5 9v7h10V9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ],
        [
            'route' => 'tenant.pos',
            'label' => 'Sell',
            'can' => Permission::CREATE_SALES,
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M3 4h2l1.5 8.5h8L16 7H6" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><circle cx="8" cy="16" r="1.2" fill="currentColor"/><circle cx="14" cy="16" r="1.2" fill="currentColor"/></svg>',
        ],
        [
            'route' => 'tenant.products',
            'label' => 'Products',
            'can' => Permission::VIEW_PRODUCTS,
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M10 3 3.5 6.2v7.6L10 17l6.5-3.2V6.2L10 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M3.5 6.2 10 9.5l6.5-3.3M10 9.5V17" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        ],
        [
            'route' => 'tenant.reports',
            'label' => 'Reports',
            'can' => Permission::VIEW_REPORTS,
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M4 16V9m4 7V4m4 12v-5m4 5V7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        ],
    ];
@endphp

@foreach ($items as $item)
    @continue (! $user?->can($item['can']))

    <x-ui.nav-item
        :href="route($item['route'])"
        :label="$item['label']"
        :icon="$item['icon']"
        :active="request()->routeIs($item['route'])" />
@endforeach
