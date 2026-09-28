<?php

use App\Domain\Branding\LogoService;
use App\Enums\Permission;
use App\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    use WithFileUploads;

    public $logo;

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        // Same gate as the shop's other settings: a cashier does not get to
        // change what the shop looks like on everyone's home screen.
        Gate::authorize(Permission::MANAGE_SETTINGS);
    }

    public function save(): void
    {
        $this->error = '';
        $this->notice = '';

        // 4MB. A logo is a logo; anything larger is a photograph, and it is
        // going to be resampled down to 512px either way.
        $this->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:4096'],
        ], [], ['logo' => 'logo']);

        try {
            app(LogoService::class)->store($this->shop(), $this->logo);

            $this->logo = null;
            $this->notice = 'Saved. Anyone who installs your shop now gets this icon.';
        } catch (\DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function remove(): void
    {
        $this->error = '';

        app(LogoService::class)->remove($this->shop());

        $this->notice = 'Removed. Back to the generated icon.';
    }

    public function currentLogo(): string
    {
        return (string) $this->shop()->logo_path;
    }

    private function shop(): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = tenant();

        return $tenant;
    }
};
?>

<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
    @php $current = $this->currentLogo(); @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Your logo</h1>
        <p class="mt-1.5 text-sm text-[var(--color-muted)]">
            This is the icon your staff see on their home screen once they
            install the till. Without one they get your shop's initial.
        </p>
    </div>

    @if ($error !== '')
        <x-ui.alert tone="negative" class="mb-5">{{ $error }}</x-ui.alert>
    @endif

    @if ($notice !== '')
        <x-ui.alert tone="positive" class="mb-5">{{ $notice }}</x-ui.alert>
    @endif

    <div class="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-5">
        <div class="flex items-center gap-5">
            {{-- The path is a content hash, so using it as the cache-buster
                 means a replaced logo shows immediately and an unchanged one
                 stays cached. --}}
            <img
                src="{{ $current !== '' ? route('tenant.logo').'?v='.substr($current, -12, 8) : route('tenant.icon', ['size' => 192]) }}"
                alt="{{ $current !== '' ? 'Your shop logo' : 'The generated icon for your shop' }}"
                width="80" height="80"
                class="size-20 shrink-0 rounded-2xl border border-[var(--color-border)] bg-[var(--color-bg)] object-contain">

            <div class="min-w-0">
                <p class="text-sm font-medium text-[var(--color-text)]">
                    {{ $current !== '' ? 'Using your logo' : 'Using the generated icon' }}
                </p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">
                    PNG, JPG or WEBP, up to 4MB. We square it off at 512&times;512
                    and leave a margin, because Android crops icons to whatever
                    shape the phone uses.
                </p>
            </div>
        </div>

        <form wire:submit="save" class="mt-5 border-t border-[var(--color-border)] pt-5">
            <x-ui.field
                label="Choose a file"
                name="logo"
                type="file"
                wire:model="logo"
                accept="image/png,image/jpeg,image/webp"
                class="file:mr-4 file:rounded-lg file:border-0 file:bg-[var(--color-accent)] file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-[var(--color-on-accent)] hover:file:opacity-90" />

            <div class="mt-4 flex items-center gap-3">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">Save logo</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </x-ui.button>

                @if ($current !== '')
                    <x-ui.button type="button" variant="ghost" wire:click="remove">
                        Remove
                    </x-ui.button>
                @endif
            </div>
        </form>
    </div>

    <p class="mt-4 text-xs text-[var(--color-muted)]">
        SVG files are not accepted. An SVG can carry code, and this file ends
        up as an icon on someone's phone.
    </p>
</div>
