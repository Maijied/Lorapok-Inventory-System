<?php

use App\Domain\Reporting\DateRange;
use App\Domain\Reporting\ReportService;
use App\Enums\Permission;
use App\Models\Tenant\Setting;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    #[Url(except: '30')]
    public string $period = '30';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        Gate::authorize('viewReports', App\Models\Tenant\Sale::class);
    }

    #[Computed]
    public function range(): DateRange
    {
        $tz = $this->service->timezone();

        if ($this->period === 'custom' && $this->from !== '' && $this->to !== '') {
            return DateRange::of($this->from, $this->to, $tz);
        }

        return match ($this->period) {
            'today' => DateRange::today($tz),
            '7' => DateRange::last(7, $tz),
            'month' => DateRange::thisMonth($tz),
            default => DateRange::last(30, $tz),
        };
    }

    #[Computed]
    public function service(): ReportService
    {
        return app(ReportService::class);
    }

    #[Computed]
    public function summary(): array
    {
        return $this->service->summary($this->range);
    }

    #[Computed]
    public function topProducts()
    {
        return $this->service->topProducts($this->range);
    }

    #[Computed]
    public function daily()
    {
        return $this->service->dailyTakings($this->range);
    }

    #[Computed]
    public function valuation(): array
    {
        return $this->service->stockValuation();
    }

    #[Computed]
    public function lowStock()
    {
        return $this->service->lowStock(10);
    }

    #[Computed]
    public function customerDues()
    {
        return $this->service->customerDues(10);
    }

    #[Computed]
    public function takingsByMethod()
    {
        return $this->service->takingsByMethod($this->range);
    }

    /** Cost and margin are commercially sensitive, so gated separately. */
    #[Computed]
    public function canSeeMargin(): bool
    {
        return auth('tenant')->user()?->can(Permission::VIEW_MARGIN) ?? false;
    }

    #[Computed]
    public function symbol(): string
    {
        return (string) Setting::get('locale.currency_symbol', '৳');
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Reports</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                {{ $this->range->start->format('j M Y') }} — {{ $this->range->end->format('j M Y') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            @foreach (['today' => 'Today', '7' => '7 days', '30' => '30 days', 'month' => 'This month'] as $value => $label)
                <button wire:click="$set('period', '{{ $value }}')" type="button"
                        @class([
                            'rounded-[var(--radius)] border px-3 py-1.5 text-sm transition-colors',
                            'border-[var(--color-accent)] text-[var(--color-text)]' => $period === $value,
                            'border-[var(--color-border)] text-[var(--color-muted)] hover:text-[var(--color-text)]' => $period !== $value,
                        ])>
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $tiles = [
                ['Sales', (string) $this->summary['sales_count'], 'completed in period'],
                ['Revenue', Money::ofMinor($this->summary['net_minor'])->format($this->symbol), 'after discounts and refunds'],
            ];

            if ($this->canSeeMargin) {
                $tiles[] = ['Gross margin', Money::ofMinor($this->summary['margin_minor'])->format($this->symbol), $this->summary['margin_pct'] . '% of revenue'];
                $tiles[] = ['Stock value', Money::ofMinor($this->valuation['value_minor'])->format($this->symbol), 'at average cost'];
            } else {
                $tiles[] = ['Collected', Money::ofMinor($this->summary['collected_minor'])->format($this->symbol), 'payments received'];
                $tiles[] = ['Outstanding', Money::ofMinor($this->summary['outstanding_minor'])->format($this->symbol), 'still owed'];
            }
        @endphp

        @foreach ($tiles as $i => [$label, $value, $hint])
            <div class="glass-panel p-5 animate-fade-slide-up stagger-{{ min($i + 1, 4) }}">
                <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold tabular text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                    {{ $value }}
                </p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-2">

        <div class="glass-panel p-6">
            <h2 class="text-sm font-medium text-[var(--color-text)]">Best sellers</h2>

            @if ($this->topProducts->isEmpty())
                <p class="mt-4 text-sm text-[var(--color-muted)]">No sales in this period.</p>
            @else
                <table class="mt-4 w-full text-left text-sm">
                    <caption class="sr-only">Products by revenue</caption>
                    <thead class="border-b border-[var(--color-border)] text-[var(--color-muted)]">
                        <tr>
                            <th scope="col" class="pb-2 font-medium">Product</th>
                            <th scope="col" class="pb-2 text-right font-medium">Qty</th>
                            <th scope="col" class="pb-2 text-right font-medium">Revenue</th>
                            @if ($this->canSeeMargin)
                                <th scope="col" class="pb-2 text-right font-medium">Margin</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->topProducts as $row)
                            <tr class="border-b border-[var(--color-border)]/50 last:border-0">
                                <td class="py-2 text-[var(--color-text)]">{{ $row->name }}</td>
                                <td class="py-2 text-right tabular font-[family-name:var(--font-mono)] text-[var(--color-muted)]">
                                    {{ rtrim(rtrim(number_format((float) $row->qty_sold, 2), '0'), '.') }}
                                </td>
                                <td class="py-2 text-right tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                                    {{ Money::ofMinor((int) $row->revenue_minor)->format($this->symbol) }}
                                </td>
                                @if ($this->canSeeMargin)
                                    <td class="py-2 text-right tabular font-[family-name:var(--font-mono)] text-[var(--color-ok)]">
                                        {{ Money::ofMinor((int) $row->margin_minor)->format($this->symbol) }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="glass-panel p-6">
            <h2 class="text-sm font-medium text-[var(--color-text)]">Takings by payment method</h2>

            @if ($this->takingsByMethod->isEmpty())
                <p class="mt-4 text-sm text-[var(--color-muted)]">No payments in this period.</p>
            @else
                <dl class="mt-4 space-y-2 text-sm">
                    @foreach ($this->takingsByMethod as $row)
                        <div class="flex justify-between">
                            <dt class="text-[var(--color-muted)]">{{ $row->method }}</dt>
                            <dd class="tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                                {{ Money::ofMinor((int) $row->amount_minor)->format($this->symbol) }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </div>

        <div class="glass-panel p-6">
            <h2 class="text-sm font-medium text-[var(--color-text)]">Running low</h2>
            <p class="mt-1 text-xs text-[var(--color-muted)]">At or below reorder level.</p>

            @if ($this->lowStock->isEmpty())
                <p class="mt-4 text-sm text-[var(--color-muted)]">Nothing needs reordering.</p>
            @else
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($this->lowStock as $row)
                        <li class="flex justify-between">
                            <span class="text-[var(--color-text)]">{{ $row->name }}</span>
                            <span class="tabular font-[family-name:var(--font-mono)] text-[var(--color-warn)]">
                                {{ rtrim(rtrim(number_format((float) $row->on_hand, 2), '0'), '.') }}
                                / {{ rtrim(rtrim(number_format((float) $row->reorder_level, 2), '0'), '.') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="glass-panel p-6">
            <h2 class="text-sm font-medium text-[var(--color-text)]">Customers who owe</h2>

            @if ($this->customerDues->isEmpty())
                <p class="mt-4 text-sm text-[var(--color-muted)]">Nothing outstanding.</p>
            @else
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($this->customerDues as $row)
                        <li class="flex justify-between">
                            <span class="text-[var(--color-text)]">
                                {{ $row->name }}
                                <span class="text-xs text-[var(--color-muted)]">({{ $row->open_sales }})</span>
                            </span>
                            <span class="tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                                {{ Money::ofMinor((int) $row->due_minor)->format($this->symbol) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @unless ($this->canSeeMargin)
        <p class="mt-6 text-xs text-[var(--color-muted)]">
            Cost and margin are hidden for your role.
        </p>
    @endunless
</div>
