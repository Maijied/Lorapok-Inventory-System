<?php

use App\Domain\Sales\CartLine;
use App\Domain\Sales\InvalidSale;
use App\Domain\Sales\RegisterService;
use App\Domain\Sales\SalesService;
use App\Domain\Stock\InsufficientStock;
use App\Domain\Stock\StockService;
use App\Enums\Permission;
use App\Enums\ProductType;
use App\Models\Tenant\CashRegister;
use App\Models\Tenant\Location;
use App\Models\Tenant\PaymentMethod;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\RegisterSession;
use App\Models\Tenant\Setting;
use App\Models\Tenant\StockItem;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    /**
     * The cart, held server-side as plain data.
     *
     * Deliberately NOT prices or totals: those are computed from the
     * catalogue when the sale is rung up. The system this replaces kept the
     * running total in browser JavaScript and posted it back to be saved.
     *
     * @var array<int, array{variant_id:int, qty:float, name:string, stock_item_ids:array<int,int>}>
     */
    public array $cart = [];

    public string $scan = '';

    public ?int $sessionId = null;

    public string $openingFloat = '0';

    public string $tender = '';

    public ?int $paymentMethodId = null;

    public ?int $lastSaleId = null;

    public function mount(): void
    {
        Gate::authorize('create', App\Models\Tenant\Sale::class);

        $this->sessionId = $this->currentSession()?->id;
        $this->paymentMethodId = PaymentMethod::where('is_active', true)
            ->orderBy('sort_order')->value('id');
    }

    private function currentSession(): ?RegisterSession
    {
        $register = CashRegister::where('is_active', true)->first();

        return $register?->openSession();
    }

    #[Computed]
    public function session(): ?RegisterSession
    {
        return $this->sessionId ? RegisterSession::find($this->sessionId) : null;
    }

    public function openShift(): void
    {
        $register = CashRegister::where('is_active', true)->firstOrFail();

        try {
            $session = app(RegisterService::class)->open(
                $register,
                Auth::guard('tenant')->user(),
                Money::parse($this->openingFloat !== '' ? $this->openingFloat : '0')->minor,
            );
            $this->sessionId = $session->id;
            unset($this->session);
        } catch (InvalidSale|InvalidArgumentException $e) {
            $this->addError('openingFloat', $e->getMessage());
        }
    }

    /**
     * Add by barcode, SKU or IMEI.
     *
     * A hardware scanner behaves as a keyboard, so this is just the search
     * box with the cursor in it — which is how a real counter works.
     */
    public function addScanned(): void
    {
        $term = trim($this->scan);

        if ($term === '') {
            return;
        }

        // An IMEI identifies one specific handset, so it both picks the
        // product and selects the unit.
        $unit = StockItem::inStock()->where('imei', $term)->orWhere('serial', $term)->first();

        if ($unit) {
            $this->addVariant($unit->product_variant_id, 1, $unit->id);
            $this->scan = '';

            return;
        }

        $variant = ProductVariant::query()
            ->where('sku', $term)
            ->orWhereHas('barcodes', fn ($q) => $q->where('barcode', $term))
            ->first();

        if (! $variant) {
            $this->addError('scan', "Nothing found for \"{$term}\".");

            return;
        }

        $this->addVariant($variant->id);
        $this->scan = '';
    }

    public function addVariant(int $variantId, float $qty = 1, ?int $stockItemId = null): void
    {
        $this->resetErrorBag();

        /** @var ProductVariant|null $variant */
        $variant = ProductVariant::with('product')->find($variantId);

        if (! $variant?->product?->is_active) {
            $this->addError('scan', 'That product is not available to sell.');

            return;
        }

        $serialised = $variant->product->type === ProductType::Serialized;

        foreach ($this->cart as $index => $line) {
            if ($line['variant_id'] !== $variantId) {
                continue;
            }

            if ($serialised) {
                if ($stockItemId === null || in_array($stockItemId, $line['stock_item_ids'], true)) {
                    // The same handset cannot be scanned onto the cart twice.
                    return;
                }

                $this->cart[$index]['stock_item_ids'][] = $stockItemId;
                $this->cart[$index]['qty'] = count($this->cart[$index]['stock_item_ids']);

                return;
            }

            $this->cart[$index]['qty'] += $qty;

            return;
        }

        $this->cart[] = [
            'variant_id' => $variantId,
            'qty' => $serialised ? 1 : $qty,
            'name' => $variant->product->name,
            'stock_item_ids' => $stockItemId ? [$stockItemId] : [],
        ];
    }

    public function updateQty(int $index, float $qty): void
    {
        if (! isset($this->cart[$index])) {
            return;
        }

        // A serialised line's quantity is however many handsets were scanned.
        if ($this->cart[$index]['stock_item_ids'] !== []) {
            return;
        }

        if ($qty <= 0) {
            $this->removeLine($index);

            return;
        }

        $this->cart[$index]['qty'] = $qty;
    }

    public function removeLine(int $index): void
    {
        unset($this->cart[$index]);
        $this->cart = array_values($this->cart);
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->tender = '';
        $this->resetErrorBag();
    }

    /**
     * An indicative total for the cashier's benefit only.
     *
     * The authoritative figure is computed by SalesService when the sale is
     * rung up; this is never sent back to the server as a price.
     */
    #[Computed]
    public function preview(): array
    {
        $subtotal = 0;
        $tax = 0;

        foreach ($this->cart as $line) {
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::with('product.taxRate')->find($line['variant_id']);

            if (! $variant) {
                continue;
            }

            $lineSubtotal = (int) round($variant->price_minor * $line['qty']);
            $bps = $variant->product?->taxRate?->is_active ? $variant->product->taxRate->rate_bps : 0;

            $subtotal += $lineSubtotal;
            $tax += (int) round($lineSubtotal * $bps / 10_000);
        }

        return ['subtotal' => $subtotal, 'tax' => $tax, 'total' => $subtotal + $tax];
    }

    #[Computed]
    public function symbol(): string
    {
        return (string) Setting::get('locale.currency_symbol', '৳');
    }

    #[Computed]
    public function methods()
    {
        return PaymentMethod::where('is_active', true)->orderBy('sort_order')->get();
    }

    #[Computed]
    public function canOverridePrice(): bool
    {
        return Gate::allows('managePrices', App\Models\Tenant\Product::class);
    }

    public function checkout(): void
    {
        $this->resetErrorBag();

        if ($this->cart === []) {
            $this->addError('cart', 'Nothing to sell.');

            return;
        }

        $user = Auth::guard('tenant')->user();
        $location = Location::default();

        $lines = array_map(
            fn (array $line) => new CartLine(
                variantId: $line['variant_id'],
                qty: (float) $line['qty'],
                stockItemIds: $line['stock_item_ids'],
            ),
            $this->cart,
        );

        try {
            $sale = app(SalesService::class)->sell(
                $lines,
                $user,
                $location->id,
                // Every till sale is idempotent, so a double submit or a
                // replayed request cannot charge twice.
                idempotencyKey: (string) str()->uuid(),
            );

            if ($this->tender !== '') {
                $payment = app(SalesService::class)->recordPayment(
                    $sale,
                    Money::parse($this->tender)->minor,
                    $this->paymentMethodId,
                    $user->id,
                );

                if ($session = $this->session) {
                    app(RegisterService::class)->recordSalePayment($session, $sale, $payment);
                }
            }

            if ($session = $this->session) {
                $sale->forceFill(['register_session_id' => $session->id])->save();
            }

            $this->lastSaleId = $sale->id;
            $this->clearCart();
            unset($this->session);
        } catch (InsufficientStock $e) {
            // A usable message, not a 500 page as the old system produced.
            $this->addError('cart', $e->getMessage());
        } catch (InvalidSale|InvalidArgumentException $e) {
            $this->addError('cart', $e->getMessage());
        }
    }

    #[Computed]
    public function suggestions()
    {
        if (mb_strlen(trim($this->scan)) < 2) {
            return collect();
        }

        return Product::query()
            ->with('variants')
            ->search($this->scan)
            ->where('is_active', true)
            ->limit(6)
            ->get();
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 lg:px-8">

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-xl font-semibold tracking-tight text-[var(--color-text)]">Point of sale</h1>

        @if ($this->session)
            <span class="rounded-full border border-[var(--color-border)] px-3 py-1 text-xs text-[var(--color-muted)]">
                Shift open · drawer
                <span class="tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                    {{ Money::ofMinor($this->session->expectedCashMinor())->format($this->symbol) }}
                </span>
            </span>
        @endif
    </div>

    @if (! $this->session)
        <div class="glass-panel mb-4 p-4">
            <p class="text-sm text-[var(--color-text)]">No shift is open on this till.</p>
            <p class="mt-1 text-xs text-[var(--color-muted)]">
                Count the float before you start; the closing variance is measured against it.
            </p>
            <div class="mt-3 flex flex-wrap items-end gap-3">
                <div>
                    <label for="float" class="mb-1.5 block text-sm text-[var(--color-muted)]">
                        Opening float ({{ $this->symbol }})
                    </label>
                    <input wire:model="openingFloat" id="float" type="text" inputmode="decimal"
                           class="w-40 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                </div>
                <button wire:click="openShift" type="button"
                        class="rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                    Open shift
                </button>
            </div>
            @error('openingFloat') <p class="mt-2 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
        </div>
    @endif

    @if ($lastSaleId)
        <div class="glass-panel mb-4 border-l-2 !border-l-[var(--color-ok)] p-4">
            <p class="text-sm text-[var(--color-ok)]">Sale completed.</p>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-[1fr_20rem]">

        <div class="glass-panel p-4">
            <label for="scan" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                Scan or type
            </label>
            {{-- A hardware scanner types and presses Enter, so keyboard entry
                 is the primary path, not the camera. --}}
            <input
                wire:model="scan"
                wire:keydown.enter="addScanned"
                id="scan"
                type="text"
                autofocus
                autocomplete="off"
                placeholder="Barcode, SKU or IMEI — then Enter"
                class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2.5 font-[family-name:var(--font-mono)] text-[var(--color-text)] placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] focus:outline-none"
            >
            @error('scan') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror

            @if ($this->suggestions->isNotEmpty())
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach ($this->suggestions as $product)
                        @php $variant = $product->variants->first(); @endphp
                        @if ($variant)
                            <button wire:click="addVariant({{ $variant->id }})" type="button"
                                    class="rounded-full border border-[var(--color-border)] px-3 py-1 text-xs text-[var(--color-muted)] hover:text-[var(--color-text)]">
                                {{ $product->name }}
                            </button>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="mt-4">
                @if ($cart === [])
                    <p class="py-10 text-center text-sm text-[var(--color-muted)]">Cart is empty.</p>
                @else
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Items in the current sale</caption>
                        <thead class="border-b border-[var(--color-border)] text-[var(--color-muted)]">
                            <tr>
                                <th scope="col" class="py-2 font-medium">Item</th>
                                <th scope="col" class="py-2 text-right font-medium">Qty</th>
                                <th scope="col" class="py-2"><span class="sr-only">Remove</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($cart as $index => $line)
                                <tr wire:key="line-{{ $index }}" class="border-b border-[var(--color-border)]/50 last:border-0">
                                    <td class="py-2 text-[var(--color-text)]">
                                        {{ $line['name'] }}
                                        @if ($line['stock_item_ids'] !== [])
                                            <span class="ml-1 text-xs text-[var(--color-muted)]">
                                                ({{ count($line['stock_item_ids']) }} unit{{ count($line['stock_item_ids']) === 1 ? '' : 's' }} selected)
                                            </span>
                                        @endif
                                    </td>
                                    <td class="py-2 text-right">
                                        @if ($line['stock_item_ids'] !== [])
                                            <span class="tabular font-[family-name:var(--font-mono)] text-[var(--color-muted)]">
                                                {{ (int) $line['qty'] }}
                                            </span>
                                        @else
                                            <input
                                                type="number" min="0" step="1" value="{{ $line['qty'] }}"
                                                wire:change="updateQty({{ $index }}, $event.target.value)"
                                                class="w-20 rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-2 py-1 text-right tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none"
                                            >
                                        @endif
                                    </td>
                                    <td class="py-2 text-right">
                                        <button wire:click="removeLine({{ $index }})" type="button"
                                                class="text-xs text-[var(--color-muted)] hover:text-[var(--color-danger)]">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="glass-panel h-fit p-4">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-[var(--color-muted)]">Subtotal</dt>
                    <dd class="tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                        {{ Money::ofMinor($this->preview['subtotal'])->format($this->symbol) }}
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-[var(--color-muted)]">Tax</dt>
                    <dd class="tabular font-[family-name:var(--font-mono)] text-[var(--color-text)]">
                        {{ Money::ofMinor($this->preview['tax'])->format($this->symbol) }}
                    </dd>
                </div>
                <div class="flex justify-between border-t border-[var(--color-border)] pt-2 text-base">
                    <dt class="font-medium text-[var(--color-text)]">Total</dt>
                    <dd class="tabular font-[family-name:var(--font-mono)] font-semibold text-[var(--color-text)]">
                        {{ Money::ofMinor($this->preview['total'])->format($this->symbol) }}
                    </dd>
                </div>
            </dl>

            {{-- Stated plainly: this panel is a guide, and the receipt is
                 whatever the server computes. --}}
            <p class="mt-2 text-xs text-[var(--color-muted)]">
                Indicative. The final price is calculated on the server.
            </p>

            <div class="mt-4 space-y-3">
                <div>
                    <label for="method" class="mb-1.5 block text-sm text-[var(--color-muted)]">Payment</label>
                    <select wire:model="paymentMethodId" id="method"
                            class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                        @foreach ($this->methods as $method)
                            <option value="{{ $method->id }}">{{ $method->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="tender" class="mb-1.5 block text-sm text-[var(--color-muted)]">
                        Amount received ({{ $this->symbol }})
                    </label>
                    <input wire:model="tender" id="tender" type="text" inputmode="decimal"
                           placeholder="Leave blank to bill later"
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                </div>
            </div>

            @error('cart') <p class="mt-3 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror

            <button wire:click="checkout" wire:loading.attr="disabled" type="button"
                    @disabled($cart === [])
                    class="mt-4 w-full rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2.5 font-medium text-white hover:opacity-90 disabled:opacity-40">
                <span wire:loading.remove wire:target="checkout">Complete sale</span>
                <span wire:loading wire:target="checkout">Working…</span>
            </button>

            @if ($cart !== [])
                <button wire:click="clearCart" type="button"
                        class="mt-2 w-full rounded-[var(--radius)] border border-[var(--color-border)] px-4 py-2 text-sm text-[var(--color-muted)] hover:text-[var(--color-text)]">
                    Clear
                </button>
            @endif
        </div>
    </div>
</div>
