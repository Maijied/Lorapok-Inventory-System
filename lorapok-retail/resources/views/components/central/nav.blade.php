@php
    // The operator panel is one screen today. Billing, audit and applications
    // arrive in later phases and are added here then — not listed early as
    // dead links.
    $items = [
        [
            'route' => 'central.shops',
            'label' => 'Shops',
            'icon' => '<svg width="18" height="18" viewBox="0 0 20 20" fill="none"><path d="M3 8 4.5 4h11L17 8M3.5 8v8h13V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M3 8a2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 4 0 2 2 0 0 0 2 0" stroke="currentColor" stroke-width="1.6"/></svg>',
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
