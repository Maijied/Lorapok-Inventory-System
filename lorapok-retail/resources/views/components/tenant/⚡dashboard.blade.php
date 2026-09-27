<?php

use App\Domain\Reporting\DateRange;
use App\Domain\Reporting\ReportService;
use App\Enums\Permission;
use App\Models\Tenant\Setting;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    #[Computed]
    public function service(): ReportService
    {
        return app(ReportService::class);
    }

    #[Computed]
    public function today(): array
    {
        return $this->service->summary(DateRange::today($this->service->timezone()));
    }

    #[Computed]
    public function valuation(): array
    {
        return $this->service->stockValuation();
    }

    #[Computed]
    public function lowStock()
    {
        return $this->service->lowStock(50);
    }

    #[Computed]
    public function symbol(): string
    {
        return (string) Setting::get('locale.currency_symbol', '৳');
    }

    /**
     * Every tile is permission-gated individually.
     *
     * A cashier has VIEW_DASHBOARD but not VIEW_MARGIN, and stock value is
     * computed at average *cost* — putting it on an ungated dashboard would
     * leak exactly the figure the permission exists to protect.
     */
    public function can(string $permission): bool
    {
        return Auth::guard('tenant')->user()?->can($permission) ?? false;
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-8 animate-fade-slide-up">
        <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">
            {{ tenant('name') }}
        </h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            {{ now($this->service->timezone())->format('l, j F Y') }}
        </p>
    </div>

    {{-- ── Today ─────────────────────────────────────────────────────── --}}
    @php
        $tiles = [];

        if ($this->can(Permission::VIEW_SALES)) {
            $tiles[] = [
                'Today’s takings',
                Money::ofMinor($this->today['net_minor'])->format($this->symbol),
                $this->today['sales_count'] . ' ' . str('sale')->plural($this->today['sales_count']),
            ];
        }

        if ($this->can(Permission::VIEW_MARGIN)) {
            $tiles[] = [
                'Today’s margin',
                Money::ofMinor($this->today['margin_minor'])->format($this->symbol),
                $this->today['margin_pct'] . '% of takings',
            ];
            $tiles[] = [
                'Stock value',
                Money::ofMinor($this->valuation['value_minor'])->format($this->symbol),
                'at average cost',
            ];
        }

        if ($this->can(Permission::VIEW_PRODUCTS)) {
            $count = $this->lowStock->count();
            $tiles[] = [
                'Needs reordering',
                // 50 is the query limit, so report it as "50+" rather than
                // implying the number is exact.
                $count >= 50 ? '50+' : (string) $count,
                $count === 0 ? 'nothing below its reorder level' : 'at or below reorder level',
            ];
        }
    @endphp

    @if ($tiles !== [])
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($tiles as $i => [$label, $value, $hint])
                <div class="glass-panel p-5 animate-fade-slide-up stagger-{{ min($i + 1, 4) }}">
                    <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-semibold tabular text-[var(--color-text)]
                              font-[family-name:var(--font-mono)]">{{ $value }}</p>
                    <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $hint }}</p>
                </div>
            @endforeach
        </div>
    @endif

    {{-- ── Quick actions ─────────────────────────────────────────────── --}}
    <div class="mt-6 flex flex-wrap gap-3 animate-fade-slide-up stagger-3">
        @if ($this->can(Permission::CREATE_SALES))
            <x-ui.button :href="route('tenant.pos')" wire:navigate>Start selling</x-ui.button>
        @endif

        @if ($this->can(Permission::MANAGE_PRODUCTS))
            <x-ui.button variant="secondary" :href="route('tenant.products.create')" wire:navigate>Add a product</x-ui.button>
        @endif

        @if ($this->can(Permission::VIEW_REPORTS))
            <x-ui.button variant="secondary" :href="route('tenant.reports')" wire:navigate>Open reports</x-ui.button>
        @endif
    </div>

    {{-- ── What needs attention ──────────────────────────────────────── --}}
    @if ($this->can(Permission::VIEW_PRODUCTS) && $this->lowStock->isNotEmpty())
        <div class="glass-panel mt-6 p-6 animate-fade-slide-up stagger-4">
            <div class="flex items-center justify-between gap-4">
                <h2 class="text-sm font-medium text-[var(--color-text)]">Running low</h2>
                <a href="{{ route('tenant.products') }}" wire:navigate
                   class="text-xs text-[var(--color-accent)] hover:underline">All products</a>
            </div>

            <ul class="mt-3 divide-y divide-[var(--color-border)]">
                @foreach ($this->lowStock->take(5) as $row)
                    <li class="flex items-center justify-between gap-4 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm text-[var(--color-text)]">{{ $row->name }}</p>
                            <p class="truncate text-xs text-[var(--color-muted)]">{{ $row->sku }}</p>
                        </div>
                        {{-- Quantities use --color-text, never --color-muted:
                             a number someone acts on has to be legible. --}}
                        <p class="shrink-0 tabular text-sm text-[var(--color-text)]
                                  font-[family-name:var(--font-mono)]">
                            {{ rtrim(rtrim(number_format((float) $row->on_hand, 2), '0'), '.') }}
                            <span class="text-[var(--color-muted)]">/
                                {{ rtrim(rtrim(number_format((float) $row->reorder_level, 2), '0'), '.') }}</span>
                        </p>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
