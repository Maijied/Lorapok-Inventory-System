<?php

use App\Domain\Verification\VerificationService;
use App\Enums\Permission;
use App\Enums\VerificationStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    use WithFileUploads;

    public string $legal_name = '';

    public string $trade_licence_no = '';

    public string $bin_tin = '';

    public string $owner_name = '';

    public string $owner_identifier = '';

    public string $address = '';

    public string $city = '';

    public $licence;

    public $identifier_front;

    public $identifier_back;

    public $owner_photo;

    public string $error = '';

    public string $notice = '';

    public function mount(): void
    {
        // Verification is the shop's legal identity, not day-to-day work.
        // Same permission that guards the shop's own settings.
        Gate::authorize(Permission::MANAGE_SETTINGS);

        $verification = $this->verification;

        // Prefilled from what was saved, so a form abandoned halfway is
        // resumed rather than restarted. These are long, and a trade licence
        // number is not something anyone types twice cheerfully.
        foreach (['legal_name', 'trade_licence_no', 'bin_tin', 'owner_name', 'owner_identifier', 'address', 'city'] as $field) {
            $this->{$field} = (string) ($verification->{$field} ?? '');
        }
    }

    #[Computed(persist: false)]
    public function verification()
    {
        return app(VerificationService::class)->forShop(tenant());
    }

    #[Computed]
    public function service(): VerificationService
    {
        return app(VerificationService::class);
    }

    public function save(): void
    {
        $this->error = '';

        $this->validate([
            'legal_name' => ['required', 'string', 'max:190'],
            'trade_licence_no' => ['required', 'string', 'max:80'],
            'bin_tin' => ['nullable', 'string', 'max:40'],
            'owner_name' => ['required', 'string', 'max:120'],
            'owner_identifier' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:190'],
            'city' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $this->service->saveDraft(tenant(), [
                'legal_name' => $this->legal_name,
                'trade_licence_no' => $this->trade_licence_no,
                'bin_tin' => $this->bin_tin ?: null,
                'owner_name' => $this->owner_name,
                'owner_identifier' => $this->owner_identifier,
                'address' => $this->address ?: null,
                'city' => $this->city ?: null,
            ]);

            unset($this->verification);
            $this->notice = 'Saved. You can come back and finish this later.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function upload(string $kind): void
    {
        $this->error = '';

        $property = match ($kind) {
            'licence' => 'licence',
            'identifier_front' => 'identifier_front',
            'identifier_back' => 'identifier_back',
            'owner_photo' => 'owner_photo',
            default => null,
        };

        if ($property === null || $this->{$property} === null) {
            return;
        }

        // 8MB covers a photograph taken on a phone without letting someone
        // fill the disk. jpg/png/pdf only — a trade licence arrives as one of
        // those, and accepting anything else invites a file nobody can open.
        $this->validate([
            $property => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:8192'],
        ], [], [$property => 'document']);

        try {
            $this->service->attachDocument(tenant(), $kind, $this->{$property});
            $this->{$property} = null;
            unset($this->verification);
            $this->notice = 'Document uploaded.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function submit(): void
    {
        $this->error = '';

        try {
            $this->service->submit(tenant());
            unset($this->verification);
            $this->notice = 'Sent. We will look at it and come back to you.';
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function can(string $permission): bool
    {
        return Auth::guard('tenant')->user()?->can($permission) ?? false;
    }
};
?>

<div class="mx-auto max-w-2xl px-4 py-8 sm:px-6 lg:px-8">
    @php $v = $this->verification; @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Verification</h1>
        <p class="mt-1.5 text-sm text-[var(--color-muted)]">
            Who your shop is, legally. We need this before we can invoice you —
            you can keep selling in the meantime.
        </p>
    </div>

    <div class="mb-6 flex items-center gap-3">
        <x-ui.badge :tone="$v->status->tone()">{{ $v->status->label() }}</x-ui.badge>
        @if ($v->submitted_at)
            <span class="text-xs text-[var(--color-muted)]">
                Sent {{ $v->submitted_at->diffForHumans() }}
            </span>
        @endif
    </div>

    @if ($error !== '')
        <x-ui.alert tone="negative" class="mb-5">{{ $error }}</x-ui.alert>
    @endif

    @if ($notice !== '')
        <x-ui.alert tone="positive" class="mb-5">{{ $notice }}</x-ui.alert>
    @endif

    @if ($v->status === VerificationStatus::Rejected && $v->rejection_reason)
        {{-- The reason comes first, before the form. Someone who has been
             rejected is here to find out why, not to scroll past it. --}}
        <x-ui.alert tone="negative" title="This needs fixing" class="mb-6">
            {{ $v->rejection_reason }}
        </x-ui.alert>
    @endif

    @if ($v->status === VerificationStatus::Verified)
        <x-ui.alert tone="positive" title="Verified" class="mb-6">
            Nothing more to do. Get in touch if any of this changes.
        </x-ui.alert>
    @endif

    @if (! $v->status->isEditable())
        <x-ui.alert tone="info" class="mb-6">
            This is with us now and cannot be changed while we are reading it.
        </x-ui.alert>
    @endif

    {{-- ── The business ──────────────────────────────────────────────── --}}
    <form wire:submit="save" class="glass-panel space-y-5 p-6">
        <fieldset @disabled(! $v->status->isEditable()) class="space-y-5">
            <legend class="sr-only">Business details</legend>

            <x-ui.field label="Business name on the trade licence" name="legal_name"
                        :required="true" wire:model="legal_name"
                        hint="Often not the name above the door." />

            <x-ui.field label="Trade licence number" name="trade_licence_no"
                        :required="true" wire:model="trade_licence_no" />

            <x-ui.field label="BIN or TIN" name="bin_tin" wire:model="bin_tin"
                        hint="Optional. Stored encrypted." />

            <x-ui.field label="Owner's full name" name="owner_name"
                        :required="true" wire:model="owner_name"
                        hint="As it appears on the NID." />

            <x-ui.field label="Owner's NID or passport number" name="owner_identifier"
                        :required="true" wire:model="owner_identifier"
                        hint="Stored encrypted. Never shown to anyone but a reviewer." />

            <x-ui.field label="Address" name="address" wire:model="address" />
            <x-ui.field label="City" name="city" wire:model="city" />

            <x-ui.button type="submit" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving…</span>
            </x-ui.button>
        </fieldset>
    </form>

    {{-- ── Documents ─────────────────────────────────────────────────── --}}
    <div class="glass-panel mt-6 p-6">
        <h2 class="text-sm font-semibold text-[var(--color-text)]">Documents</h2>
        <p class="mt-1 text-xs text-[var(--color-muted)]">
            A photograph from your phone is fine. JPG, PNG or PDF, up to 8MB.
            Only a reviewer ever sees these, and every time one is opened is recorded.
        </p>

        <div class="mt-4 space-y-4">
            @foreach ([
                'licence' => ['Trade licence', true],
                'identifier_front' => ['NID or passport, front', true],
                'identifier_back' => ['NID, back', false],
                'owner_photo' => ["Owner's photograph", false],
            ] as $kind => [$label, $required])
                <div class="flex flex-wrap items-end gap-3">
                    <label class="flex-1 min-w-[12rem]">
                        <span class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                            {{ $label }}
                            @if ($required)
                                <span class="text-[var(--r-negative)]" aria-hidden="true">*</span>
                                <span class="sr-only">(required)</span>
                            @endif
                        </span>
                        <input type="file" wire:model="{{ $kind }}"
                               accept=".jpg,.jpeg,.png,.pdf"
                               @disabled(! $v->status->isEditable())
                               class="w-full text-sm text-[var(--color-muted)] file:mr-3 file:rounded-[var(--radius)] file:border file:border-[var(--color-border)] file:bg-transparent file:px-3 file:py-1.5 file:text-sm file:text-[var(--color-text)]">
                    </label>

                    @if ($v->documents()[$kind] ?? null)
                        {{-- Confirms we hold it without offering it back: a
                             shop re-downloading its own NID scan from us is a
                             copy of it in one more place. --}}
                        <x-ui.badge tone="positive">Received</x-ui.badge>
                    @endif

                    @if ($this->{$kind} && $v->status->isEditable())
                        <x-ui.button size="sm" wire:click="upload('{{ $kind }}')"
                                     wire:loading.attr="disabled">
                            Upload
                        </x-ui.button>
                    @endif
                </div>

                @error($kind)
                    <p class="text-sm text-[var(--r-negative)]">{{ $message }}</p>
                @enderror
            @endforeach
        </div>
    </div>

    {{-- ── Send ──────────────────────────────────────────────────────── --}}
    @if ($v->status->isEditable())
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-ui.button size="lg" wire:click="submit" wire:loading.attr="disabled">
                Send for review
            </x-ui.button>

            @unless ($v->isSubmittable())
                <span class="text-sm text-[var(--color-muted)]">
                    Still missing something required.
                </span>
            @endunless
        </div>
    @endif
</div>
