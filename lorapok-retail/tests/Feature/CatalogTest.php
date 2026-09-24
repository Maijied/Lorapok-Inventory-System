<?php

declare(strict_types=1);

use App\Enums\ProductType;
use App\Models\Tenant;
use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\User;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->createdTenants = collect();

    $this->tenant = Tenant::create(['name' => 'Karim Mobile', 'slug' => 'karim']);
    $this->tenant->domains()->create(['domain' => 'karim.lorapok.localhost']);
    $this->createdTenants->push($this->tenant);

    tenancy()->initialize($this->tenant);

    $this->owner = User::create([
        'name' => 'Owner', 'email' => 'owner@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['owner']);

    $this->cashier = User::create([
        'name' => 'Cashier', 'email' => 'cashier@karim.test',
        'password' => 'password', 'is_active' => true,
    ])->syncRoles(['cashier']);
});

afterEach(function () {
    tenancy()->end();

    $this->createdTenants->each(function (Tenant $tenant) {
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (Throwable) {
            // Already gone.
        }
        Tenant::withoutEvents(fn () => $tenant->delete());
    });
});

/** Create a product with its default variant. */
function makeProduct(string $name, string $sku, int $costMinor = 1000, int $priceMinor = 2000): Product
{
    $product = Product::create([
        'name' => $name, 'sku' => $sku, 'slug' => Str::slug($name),
        'type' => ProductType::Standard, 'track_stock' => true,
        'reorder_level' => 0, 'is_active' => true,
    ]);

    ProductVariant::create([
        'product_id' => $product->id, 'sku' => $sku,
        'cost_minor' => $costMinor, 'price_minor' => $priceMinor, 'is_active' => true,
    ]);

    return $product;
}

it('stores prices as integer minor units', function () {
    actingAsTenantUser($this->owner);

    Livewire::test('tenant.catalog.product-form')
        ->set('name', 'USB-C Cable')
        ->set('sku', 'CBL-1')
        ->set('cost', '180.50')
        ->set('price', '350.00')
        ->call('save')
        ->assertHasNoErrors();

    $variant = ProductVariant::where('sku', 'CBL-1')->firstOrFail();

    // 180.50 is stored as 18050 paisa, not as a float.
    expect($variant->cost_minor)->toBe(18050)
        ->and($variant->price_minor)->toBe(35000)
        ->and($variant->cost_minor)->toBeInt();
});

it('escapes HTML in product descriptions', function () {
    // Regression test. The old system stored raw WYSIWYG HTML and echoed it
    // with {!! !!}, so any staff member could inject a script that ran for
    // everyone else. Descriptions are Markdown now and HTML is escaped.
    $product = makeProduct('Malicious', 'MAL-1');
    $product->update(['description' => 'Nice phone <script>alert(1)</script> **bold**']);

    $html = (string) $product->fresh()->renderedDescription();

    expect($html)->not->toContain('<script>')
        ->and($html)->toContain('&lt;script&gt;')
        // Markdown still renders, so the feature is not lost.
        ->and($html)->toContain('<strong>bold</strong>');
});

it('rejects a duplicate SKU', function () {
    actingAsTenantUser($this->owner);
    makeProduct('Existing', 'DUP-1');

    Livewire::test('tenant.catalog.product-form')
        ->set('name', 'Another')
        ->set('sku', 'DUP-1')
        ->set('cost', '10')
        ->set('price', '20')
        ->call('save')
        ->assertHasErrors('sku');
});

it('requires a name, sku and prices', function () {
    actingAsTenantUser($this->owner);

    // The old system had zero server-side validation on products: blank
    // submissions silently created empty rows.
    Livewire::test('tenant.catalog.product-form')
        ->set('name', '')
        ->set('sku', '')
        ->set('cost', '')
        ->set('price', '')
        ->call('save')
        ->assertHasErrors(['name', 'sku', 'cost', 'price']);
});

it('filters products server-side by name, sku and barcode', function () {
    actingAsTenantUser($this->owner);

    makeProduct('iPhone Fifteen', 'IP15');
    makeProduct('Galaxy Alpha', 'SGA55');
    $cable = makeProduct('Charging Cable', 'CBL-1');
    $cable->variants->first()->barcodes()->create(['barcode' => '8991234567890', 'type' => 'ean13']);

    $component = Livewire::test('tenant.catalog.products');

    $component->set('search', 'iPhone')
        ->assertSee('iPhone Fifteen')
        ->assertDontSee('Galaxy Alpha');

    $component->set('search', 'SGA')
        ->assertSee('Galaxy Alpha')
        ->assertDontSee('iPhone Fifteen');

    // Scanning a barcode into the search box finds the product.
    $component->set('search', '8991234567890')
        ->assertSee('Charging Cable')
        ->assertDontSee('iPhone Fifteen');
});

it('hides inactive products unless asked for', function () {
    actingAsTenantUser($this->owner);

    makeProduct('Active Widget', 'ACT-1');
    makeProduct('Retired Widget', 'OLD-1')->update(['is_active' => false]);

    Livewire::test('tenant.catalog.products')
        ->assertSee('Active Widget')
        ->assertDontSee('Retired Widget')
        ->set('onlyInactive', true)
        ->assertSee('Retired Widget')
        ->assertDontSee('Active Widget');
});

it('paginates rather than loading every row', function () {
    actingAsTenantUser($this->owner);

    // Zero-padded so alphabetical ordering is predictable.
    foreach (range(1, 20) as $i) {
        makeProduct('Product '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), "SKU-{$i}");
    }

    // The old system sent the entire table to the browser on every page load.
    Livewire::test('tenant.catalog.products')
        ->assertSee('Product 01')
        ->assertSee('Product 15')
        ->assertDontSee('Product 16')
        ->set('paginators.page', 2)
        ->assertSee('Product 16')
        ->assertDontSee('Product 01');
});

it('resets to the first page when the search changes', function () {
    actingAsTenantUser($this->owner);

    foreach (range(1, 20) as $i) {
        makeProduct('Product '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), "SKU-{$i}");
    }

    // Filtering while on page 2 would otherwise show an empty list.
    Livewire::test('tenant.catalog.products')
        ->set('paginators.page', 2)
        ->set('search', 'Product 0')
        ->assertSet('paginators.page', 1);
});

it('does not let a cashier open the product form', function () {
    actingAsTenantUser($this->cashier);

    // Asserted over HTTP rather than on the component: Livewire converts the
    // AuthorizationException into a 403 response instead of letting it escape.
    $this->get('http://karim.lorapok.localhost/products/create')
        ->assertForbidden();
});

it('lets an owner open the product form', function () {
    actingAsTenantUser($this->owner);

    $this->get('http://karim.lorapok.localhost/products/create')
        ->assertOk()
        ->assertSee('Add product');
});

it('hides cost price from a cashier', function () {
    actingAsTenantUser($this->cashier);
    makeProduct('Phone', 'PH-1', costMinor: 50_000, priceMinor: 70_000);

    // Cost reveals margin, which a cashier has no business seeing.
    Livewire::test('tenant.catalog.products')
        ->assertSee('700.00')
        ->assertDontSee('500.00')
        ->assertDontSee('Add product');
});

it('lets an owner see cost price', function () {
    actingAsTenantUser($this->owner);
    makeProduct('Phone', 'PH-1', costMinor: 50_000, priceMinor: 70_000);

    Livewire::test('tenant.catalog.products')
        ->assertSee('500.00')
        ->assertSee('700.00')
        ->assertSee('Add product');
});

it('keeps each shop catalogue separate', function () {
    makeProduct('Karim Phone', 'K-1');

    tenancy()->end();

    $other = Tenant::create(['name' => 'Rahim Electronics', 'slug' => 'rahim']);
    $other->domains()->create(['domain' => 'rahim.lorapok.localhost']);
    $this->createdTenants->push($other);

    tenancy()->initialize($other);
    expect(Product::count())->toBe(0);

    makeProduct('Rahim Phone', 'R-1');
    expect(Product::pluck('sku')->all())->toBe(['R-1']);

    tenancy()->end();
    tenancy()->initialize($this->tenant);
    expect(Product::pluck('sku')->all())->toBe(['K-1']);
});

it('seeds catalogue defaults for a new shop', function () {
    // The old system shipped a categories table with no UI and no seed data,
    // so the dashboard rendered blank cards for names that existed only in a
    // Blade template.
    expect(Category::count())->toBeGreaterThan(0)
        ->and(Category::where('slug', 'mobile-phone')->exists())->toBeTrue();
});
