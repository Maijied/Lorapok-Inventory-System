@php
    // Only routes that exist are listed. Billing and applications arrive in
    // later phases and are added here then — not listed early as dead links.
    $items = [
        [
            'route' => 'central.shops',
            'label' => 'Shops',
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M3 8 4.5 4h11L17 8M3.5 8v8h13V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 8a2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 2 0" stroke="currentColor" stroke-width="1.6"/></svg>',
        ],
        [
            'route' => 'central.audit',
            'label' => 'Audit',
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M5 3h7l3 3v11H5V3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 3v3h3M7.5 10h5M7.5 13h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        ],
        [
            'route' => 'central.billing',
            'label' => 'Billing',
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><rect x="3" y="5" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M3 8.5h14" stroke="currentColor" stroke-width="1.6"/><path d="M6 12h3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        ],
        [
            'route' => 'central.verifications',
            'label' => 'Verification',
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M10 3 4 5.5v4.2c0 3.1 2.4 5.9 6 7.3 3.6-1.4 6-4.2 6-7.3V5.5L10 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="m7.5 10 1.8 1.8L13 8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ],
    ];
@endphp

@foreach ($items as $item)
    <x-ui.nav-item
        :href="route($item['route'])"
        :label="$item['label']"
        :icon="$item['icon']"
        :active="request()->routeIs($item['route'])" />
@endforeach
