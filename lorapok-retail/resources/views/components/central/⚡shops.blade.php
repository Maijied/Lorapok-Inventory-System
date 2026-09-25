<?php

use App\Domain\Central\InvalidShop;
use App\Domain\Central\ShopProvisioner;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('components.layouts.central')]
class extends Component
{
    #[Url(as: 'q', except: '')]
    public string $search = '';

    public bool $creating = false;

    public string $name = '';

    public string $slug = '';

    public string $ownerName = '';

    public string $ownerEmail = '';

    public string $ownerPassword = '';

    public string $accent = '';

    public function mount(): void
    {
        abort_unless(Auth::guard('web')->user()?->isSuperAdmin(), 403);

        $this->accent = Tenant::ACCENT_PALETTE[0];
    }

    /** Suggest an address from the shop name, which the operator can edit. */
    public function updatedName(string $value): void
    {
        if (! $this->creating) {
            return;
        }

        $this->slug = Str::slug($value);
    }

    public function create(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:32'],
            'ownerName' => ['required', 'string', 'max:120'],
            'ownerEmail' => ['required', 'email'],
            'ownerPassword' => ['required', 'string', 'min:8'],
        ]);

        try {
            app(ShopProvisioner::class)->create(
                name: $this->name,
                slug: $this->slug,
                ownerName: $this->ownerName,
                ownerEmail: $this->ownerEmail,
                ownerPassword: $this->ownerPassword,
                accent: $this->accent,
                actor: Auth::guard('web')->user(),
            );
        } catch (InvalidShop $e) {
            // The address is what almost always fails, so the message lands
            // on that field rather than in a general banner.
            $this->addError('slug', $e->getMessage());

            return;
        }

        $this->reset(['name', 'slug', 'ownerName', 'ownerEmail', 'ownerPassword', 'creating']);
        $this->accent = Tenant::ACCENT_PALETTE[0];
        unset($this->shops);

        session()->flash('status', 'Shop created.');
    }

    public function suspend(string $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        app(ShopProvisioner::class)->suspend($tenant, actor: Auth::guard('web')->user());
        unset($this->shops);
    }

    public function restore(string $tenantId): void
    {
        $tenant = Tenant::findOrFail($tenantId);
        app(ShopProvisioner::class)->restore($tenant, actor: Auth::guard('web')->user());
        unset($this->shops);
    }

    #[Computed]
    public function shops()
    {
        return Tenant::query()
            ->when($this->search !== '', fn ($q) => $q
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('slug', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->get();
    }

    /**
     * Headline figures read from the central rollup table only.
     *
     * Never a query per shop: that would cost one connection each and get
     * slower with every shop added.
     */
    #[Computed]
    public function totals(): array
    {
        $row = DB::table('tenant_daily_metrics')
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('
                COALESCE(SUM(sales_count), 0) as sales_count,
                COALESCE(SUM(sales_net_minor), 0) as net,
                COALESCE(SUM(gross_margin_minor), 0) as margin,
                COUNT(DISTINCT currency) as currencies
            ')
            ->first();

        $latest = DB::table('tenant_daily_metrics')->max('computed_at');

        return [
            'sales_count' => (int) ($row->sales_count ?? 0),
            'net' => (int) ($row->net ?? 0),
            'margin' => (int) ($row->margin ?? 0),
            // Summing across currencies without an FX table would be quietly
            // wrong, so the dashboard says so instead of pretending.
            'mixed_currency' => (int) ($row->currencies ?? 0) > 1,
            'computed_at' => $latest,
        ];
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Shops</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                {{ $this->shops->count() }} {{ Str::plural('shop', $this->shops->count()) }} on Lorapok Retail
            </p>
        </div>

        <button wire:click="$toggle('creating')" type="button"
                class="rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2 text-sm font-medium text-white hover:opacity-90">
            {{ $creating ? 'Cancel' : 'New shop' }}
        </button>
    </div>

    @if (session('status'))
        <div class="glass-panel mb-4 border-l-2 !border-l-[var(--color-ok)] p-4 text-sm text-[var(--color-ok)]">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        @php
            $tiles = [
                ['Sales (30 days)', (string) $this->totals['sales_count'], 'across all shops'],
                ['Revenue', $this->totals['mixed_currency'] ? '—' : Money::ofMinor($this->totals['net'])->format('৳'), $this->totals['mixed_currency'] ? 'mixed currencies' : 'net of refunds'],
                ['Margin', $this->totals['mixed_currency'] ? '—' : Money::ofMinor($this->totals['margin'])->format('৳'), 'gross'],
            ];
        @endphp

        @foreach ($tiles as $i => [$label, $value, $hint])
            <div class="glass-panel p-5 animate-fade-slide-up stagger-{{ $i + 1 }}">
                <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                <p class="mt-2 text-2xl font-semibold tabular text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                    {{ $value }}
                </p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    {{-- Staleness is stated, not hidden: these are rollups, not live queries. --}}
    <p class="mb-6 text-xs text-[var(--color-muted)]">
        @if ($this->totals['computed_at'])
            Figures as of {{ \Carbon\Carbon::parse($this->totals['computed_at'])->diffForHumans() }}.
            Run <code class="font-[family-name:var(--font-mono)]">metrics:rollup</code> to refresh.
        @else
            No metrics yet — run <code class="font-[family-name:var(--font-mono)]">metrics:rollup</code>.
        @endif
    </p>

    @if ($creating)
        <form wire:submit="create" class="glass-panel mb-6 space-y-5 p-6">
            <h2 class="text-sm font-medium text-[var(--color-text)]">New shop</h2>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="name" class="mb-1.5 block text-sm text-[var(--color-text)]">Shop name</label>
                    <input wire:model.live="name" id="name" type="text" required
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('name') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="slug" class="mb-1.5 block text-sm text-[var(--color-text)]">Address</label>
                    <div class="flex items-center gap-1">
                        <input wire:model="slug" id="slug" type="text" required
                               class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                        <span class="whitespace-nowrap text-xs text-[var(--color-muted)]">
                            .{{ config('tenancy.root_domain') }}
                        </span>
                    </div>
                    @error('slug') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ownerName" class="mb-1.5 block text-sm text-[var(--color-text)]">Owner name</label>
                    <input wire:model="ownerName" id="ownerName" type="text" required
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('ownerName') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ownerEmail" class="mb-1.5 block text-sm text-[var(--color-text)]">Owner email</label>
                    <input wire:model="ownerEmail" id="ownerEmail" type="email" required
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('ownerEmail') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="ownerPassword" class="mb-1.5 block text-sm text-[var(--color-text)]">Owner password</label>
                    <input wire:model="ownerPassword" id="ownerPassword" type="password" required
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('ownerPassword') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <span class="mb-1.5 block text-sm text-[var(--color-text)]">Accent</span>
                    {{-- A fixed palette rather than a colour picker: every
                         option is contrast-checked, and the value ends up
                         inside a style attribute. --}}
                    <div class="flex flex-wrap gap-2">
                        @foreach (Tenant::ACCENT_PALETTE as $colour)
                            <button wire:click="$set('accent', '{{ $colour }}')" type="button"
                                    aria-label="Use {{ $colour }}"
                                    @class(['h-7 w-7 rounded-full border-2', 'border-white' => $accent === $colour, 'border-transparent' => $accent !== $colour])
                                    style="background: {{ $colour }}"></button>
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2.5 font-medium text-white hover:opacity-90 disabled:opacity-60">
                <span wire:loading.remove wire:target="create">Create shop</span>
                <span wire:loading wire:target="create">Provisioning…</span>
            </button>
        </form>
    @endif

    <div class="glass-panel overflow-hidden">
        @if ($this->shops->isEmpty())
            <p class="px-6 py-16 text-center text-sm text-[var(--color-muted)]">No shops yet.</p>
        @else
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Shops on the platform</caption>
                <thead class="border-b border-[var(--color-border)] text-[var(--color-muted)]">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Shop</th>
                        <th scope="col" class="px-4 py-3 font-medium">Address</th>
                        <th scope="col" class="px-4 py-3 font-medium">Status</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->shops as $shop)
                        <tr wire:key="shop-{{ $shop->id }}" class="border-b border-[var(--color-border)]/50 last:border-0">
                            <td class="px-4 py-3">
                                <span class="inline-block h-2 w-2 rounded-full align-middle" style="background: {{ $shop->accent }}"></span>
                                <span class="ml-2 text-[var(--color-text)]">{{ $shop->name }}</span>
                            </td>
                            <td class="px-4 py-3 font-[family-name:var(--font-mono)] text-[var(--color-muted)]">
                                {{ $shop->slug }}
                            </td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'rounded-full border px-2 py-0.5 text-xs',
                                    'border-[var(--color-danger)] text-[var(--color-danger)]' => $shop->isSuspended(),
                                    'border-[var(--color-border)] text-[var(--color-muted)]' => ! $shop->isSuspended(),
                                ])>{{ $shop->status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($shop->isSuspended())
                                    <button wire:click="restore('{{ $shop->id }}')" type="button"
                                            class="text-xs text-[var(--color-ok)] hover:underline">Restore</button>
                                @else
                                    <button wire:click="suspend('{{ $shop->id }}')" type="button"
                                            class="text-xs text-[var(--color-muted)] hover:text-[var(--color-danger)]">Suspend</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
