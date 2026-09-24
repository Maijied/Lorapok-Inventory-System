<?php

use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use App\Support\Money;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: false)]
    public bool $onlyInactive = false;

    public function mount(): void
    {
        // Every component asserts its own authorisation. An architecture test
        // fails the build if one forgets.
        Gate::authorize('viewAny', Product::class);
    }

    public function updatedSearch(): void
    {
        // Without this, filtering while on page 3 shows an empty list.
        $this->resetPage();
    }

    public function updatedType(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'type', 'onlyInactive']);
        $this->resetPage();
    }

    /**
     * Exposed as a computed property rather than via with(): a single-file
     * component renders an inline template, so there is no view object for
     * tests to read data back from.
     */
    #[Computed]
    public function products()
    {
        return Product::query()
            ->with(['category', 'brand', 'variants'])
            // Paginated and filtered in SQL. The old system sent every row to
            // the browser and filtered client-side, which stops working well
            // before a real catalogue size.
            ->search($this->search)
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->onlyInactive,
                fn ($q) => $q->where('is_active', false),
                fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(15);
    }

    #[Computed]
    public function canSeeCost(): bool
    {
        return Gate::allows('viewCost', Product::class);
    }

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('create', Product::class);
    }

    #[Computed]
    public function currencySymbol(): string
    {
        return Setting::get('locale.currency_symbol', '৳');
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Products</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                {{ $this->products->total() }} {{ Str::plural('product', $this->products->total()) }}
            </p>
        </div>

        @if ($this->canManage)
            <a href="{{ route('tenant.products.create') }}" wire:navigate
               class="rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Add product
            </a>
        @endif
    </div>

    <div class="glass-panel mb-6 p-4">
        <div class="flex flex-wrap gap-3">
            <div class="min-w-[220px] flex-1">
                <label for="search" class="sr-only">Search products</label>
                <input
                    wire:model.live.debounce.300ms="search"
                    id="search"
                    type="search"
                    placeholder="Search name, SKU or scan a barcode…"
                    class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] focus:outline-none"
                >
            </div>

            <div>
                <label for="type" class="sr-only">Filter by type</label>
                <select wire:model.live="type" id="type"
                        class="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    <option value="">All types</option>
                    @foreach (App\Enums\ProductType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
            </div>

            <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                <input wire:model.live="onlyInactive" type="checkbox"
                       class="rounded border-[var(--color-border)] bg-transparent text-[var(--color-accent)]">
                Show inactive
            </label>

            @if ($search !== '' || $type !== '' || $onlyInactive)
                <button wire:click="clearFilters" type="button"
                        class="rounded-[var(--radius)] border border-[var(--color-border)] px-3 py-2 text-sm text-[var(--color-muted)] hover:text-[var(--color-text)]">
                    Clear
                </button>
            @endif
        </div>
    </div>

    <div class="glass-panel overflow-hidden" wire:loading.class="opacity-60">
        @if ($this->products->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-[var(--color-text)]">No products found</p>
                <p class="mt-1 text-sm text-[var(--color-muted)]">
                    {{ $search !== '' ? 'Try a different search term.' : 'Add your first product to get started.' }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Products in this shop</caption>
                    <thead class="border-b border-[var(--color-border)] text-[var(--color-muted)]">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Product</th>
                            <th scope="col" class="px-4 py-3 font-medium">SKU</th>
                            <th scope="col" class="px-4 py-3 font-medium">Category</th>
                            <th scope="col" class="px-4 py-3 font-medium">Type</th>
                            @if ($this->canSeeCost)
                                <th scope="col" class="px-4 py-3 text-right font-medium">Cost</th>
                            @endif
                            <th scope="col" class="px-4 py-3 text-right font-medium">Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->products as $product)
                            @php $variant = $product->variants->first(); @endphp
                            <tr wire:key="product-{{ $product->id }}"
                                class="border-b border-[var(--color-border)]/50 last:border-0 hover:bg-[var(--color-bg-surface-hover)]/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('tenant.products.edit', $product) }}" wire:navigate
                                       class="text-[var(--color-text)] hover:text-[var(--color-accent)]">
                                        {{ $product->name }}
                                    </a>
                                    @unless ($product->is_active)
                                        <span class="ml-2 rounded-full border border-[var(--color-border)] px-2 py-0.5 text-xs text-[var(--color-muted)]">
                                            Inactive
                                        </span>
                                    @endunless
                                </td>
                                <td class="px-4 py-3 font-[family-name:var(--font-mono)] text-[var(--color-muted)]">
                                    {{ $product->sku }}
                                </td>
                                <td class="px-4 py-3 text-[var(--color-muted)]">
                                    {{ $product->category?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-[var(--color-muted)]">
                                    {{ $product->type->label() }}
                                </td>
                                @if ($this->canSeeCost)
                                    <td class="px-4 py-3 text-right tabular font-[family-name:var(--font-mono)] text-[var(--color-muted)]">
                                        {{-- An em dash, never a fabricated zero, when there is no figure. --}}
                                        {{ $variant ? Money::ofMinor($variant->cost_minor)->format($this->currencySymbol) : '—' }}
                                    </td>
                                @endif
                                <td class="px-4 py-3 text-right tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                                    {{ $variant ? Money::ofMinor($variant->price_minor)->format($this->currencySymbol) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($this->products->hasPages())
        <div class="mt-4">{{ $this->products->links() }}</div>
    @endif
</div>
