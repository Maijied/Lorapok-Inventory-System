<?php

use App\Enums\ProductType;
use App\Models\Tenant\Brand;
use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Unit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    /**
     * The product being edited, held as an id rather than a model instance.
     * Livewire serialises public properties between requests, and a hydrated
     * Eloquent model on a public property produces "Invalid Livewire snapshot
     * structure" once the component round-trips.
     */
    public ?int $productId = null;

    public string $name = '';
    public string $sku = '';
    public string $description = '';
    public string $type = 'standard';
    public ?int $category_id = null;
    public ?int $brand_id = null;
    public ?int $unit_id = null;
    public string $reorder_level = '0';
    public string $warranty_days = '';
    public bool $is_active = true;

    // Money is captured as a decimal string and converted to integer minor
    // units on save. It is never held as a float.
    public string $cost = '';
    public string $price = '';

    public function mount(): void
    {
        // Resolved from the route rather than a mount parameter: Livewire
        // stores mount arguments in the component memo, and an Eloquent model
        // there makes the snapshot unserialisable on the next round-trip
        // ("Invalid Livewire snapshot structure").
        $routeProduct = request()->route('product');
        $existing = $routeProduct instanceof Product && $routeProduct->exists ? $routeProduct : null;
        $this->productId = $existing?->id;

        Gate::authorize($existing ? 'update' : 'create', $existing ?? Product::class);

        if (! $existing) {
            $this->unit_id = Unit::where('code', 'pc')->value('id');

            return;
        }

        $this->fill($existing->only([
            'name', 'sku', 'description', 'category_id', 'brand_id', 'unit_id', 'is_active',
        ]));
        $this->type = $existing->type->value;
        $this->reorder_level = (string) $existing->reorder_level;
        $this->warranty_days = (string) ($existing->warranty_days ?? '');

        if ($variant = $existing->variants->first()) {
            $this->cost = Money::ofMinor($variant->cost_minor)->toDecimal();
            $this->price = Money::ofMinor($variant->price_minor)->toDecimal();
        }
    }

    private function product(): ?Product
    {
        return $this->productId ? Product::find($this->productId) : null;
    }

    protected function rules(): array
    {
        $ignore = $this->productId;

        return [
            'name' => ['required', 'string', 'max:255'],
            // Uniqueness is enforced in the database too; this produces a
            // readable message instead of a constraint violation.
            'sku' => ['required', 'string', 'max:64', "unique:products,sku,{$ignore}"],
            'description' => ['nullable', 'string', 'max:20000'],
            'type' => ['required', 'string', 'in:'.implode(',', array_column(ProductType::cases(), 'value'))],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'cost' => ['required', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ];
    }

    public function save(): void
    {
        $existing = $this->product();

        Gate::authorize($existing ? 'update' : 'create', $existing ?? Product::class);

        $validated = $this->validate();

        $cost = Money::parse($this->cost);
        $price = Money::parse($this->price);

        // Selling below cost is usually a typo, but it is a legitimate
        // clearance decision — warn rather than block, and only for someone
        // allowed to see cost at all.
        if ($price->minor < $cost->minor && Gate::allows('viewCost', Product::class)) {
            session()->flash('warning', 'Selling price is below cost price.');
        }

        $created = $existing === null;

        DB::transaction(function () use ($cost, $price, $validated, $existing) {
            $product = $existing ?? new Product;

            $product->fill([
                ...$validated,
                'slug' => $product->slug ?: Str::slug($this->name).'-'.Str::random(4),
                'track_stock' => ProductType::from($this->type)->tracksStock(),
                'warranty_days' => $this->warranty_days === '' ? null : (int) $this->warranty_days,
            ])->save();

            // v1 exposes a single default variant per product; the schema
            // supports many so multi-variant products need no migration later.
            $variant = $product->variants()->first() ?? new ProductVariant(['product_id' => $product->id]);
            $variant->fill([
                'product_id' => $product->id,
                'sku' => $variant->sku ?: $product->sku,
                'cost_minor' => $cost->minor,
                'price_minor' => $price->minor,
                'is_active' => $product->is_active,
            ])->save();

            $this->productId = $product->id;
        });

        session()->flash('status', $created ? 'Product created.' : 'Product updated.');

        $this->redirect(route('tenant.products'), navigate: true);
    }

    #[Computed]
    public function canSeeCost(): bool
    {
        return Gate::allows('viewCost', Product::class);
    }

    #[Computed]
    public function currencySymbol(): string
    {
        return Setting::get('locale.currency_symbol', '৳');
    }

    public function with(): array
    {
        return [
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'brands' => Brand::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
        ];
    }
};
?>

<div class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('tenant.products') }}" wire:navigate
           class="text-sm text-[var(--color-muted)] hover:text-[var(--color-text)]">← Back to products</a>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight text-[var(--color-text)]">
            {{ $productId ? 'Edit product' : 'Add product' }}
        </h1>
    </div>

    @if (session('warning'))
        <div class="glass-panel mb-4 border-l-2 !border-l-[var(--color-warn)] p-4 text-sm text-[var(--color-warn)]">
            {{ session('warning') }}
        </div>
    @endif

    <form wire:submit="save" class="glass-panel space-y-5 p-6">

        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label for="name" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Name</label>
                <input wire:model="name" id="name" type="text" required
                       class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                @error('name') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="sku" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">SKU</label>
                <input wire:model="sku" id="sku" type="text" required
                       class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                @error('sku') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="type" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Type</label>
                <select wire:model="type" id="type"
                        class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @foreach (ProductType::cases() as $case)
                        <option value="{{ $case->value }}">{{ $case->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-xs text-[var(--color-muted)]">
                    Serialised products require an IMEI or serial for every unit sold.
                </p>
            </div>

            <div>
                <label for="category_id" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Category</label>
                <select wire:model="category_id" id="category_id"
                        class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    <option value="">—</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="brand_id" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Brand</label>
                <select wire:model="brand_id" id="brand_id"
                        class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    <option value="">—</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand->id }}">{{ $brand->name }}</option>
                    @endforeach
                </select>
            </div>

            @if ($this->canSeeCost)
                <div>
                    <label for="cost" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                        Cost price ({{ $this->currencySymbol }})
                    </label>
                    <input wire:model="cost" id="cost" type="text" inputmode="decimal" required
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('cost') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-[var(--color-muted)]">Required to compute profit margin.</p>
                </div>
            @endif

            <div>
                <label for="price" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                    Selling price ({{ $this->currencySymbol }})
                </label>
                <input wire:model="price" id="price" type="text" inputmode="decimal" required
                       class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                @error('price') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="reorder_level" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Reorder level</label>
                <input wire:model="reorder_level" id="reorder_level" type="text" inputmode="decimal"
                       class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                @error('reorder_level') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="warranty_days" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Warranty (days)</label>
                <input wire:model="warranty_days" id="warranty_days" type="text" inputmode="numeric" placeholder="Shop default"
                       class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 tabular font-[family-name:var(--font-mono)] text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                @error('warranty_days') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
            </div>

            <div class="sm:col-span-2">
                <label for="description" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Description</label>
                <textarea wire:model="description" id="description" rows="4"
                          class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none"></textarea>
                <p class="mt-1.5 text-xs text-[var(--color-muted)]">
                    Markdown supported. HTML is escaped, not rendered.
                </p>
                @error('description') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm text-[var(--color-muted)] sm:col-span-2">
                <input wire:model="is_active" type="checkbox"
                       class="rounded border-[var(--color-border)] bg-transparent text-[var(--color-accent)]">
                Active — available to sell
            </label>
        </div>

        <div class="flex items-center gap-3 border-t border-[var(--color-border)] pt-5">
            <button type="submit" wire:loading.attr="disabled"
                    class="rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2.5 font-medium text-white hover:opacity-90 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">{{ $productId ? 'Save changes' : 'Create product' }}</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
            <a href="{{ route('tenant.products') }}" wire:navigate
               class="text-sm text-[var(--color-muted)] hover:text-[var(--color-text)]">Cancel</a>
        </div>
    </form>
</div>
