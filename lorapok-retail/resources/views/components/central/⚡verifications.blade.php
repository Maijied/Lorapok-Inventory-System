<?php

use App\Domain\Verification\VerificationService;
use App\Enums\VerificationStatus;
use App\Models\ShopVerification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('components.layouts.central')]
class extends Component
{
    #[Url(except: 'submitted')]
    public string $status = 'submitted';

    public ?int $reviewing = null;

    public string $rejectionReason = '';

    public string $error = '';

    #[Computed]
    public function service(): VerificationService
    {
        return app(VerificationService::class);
    }

    #[Computed]
    public function queue()
    {
        return ShopVerification::query()
            ->with('tenant')
            ->when($this->status !== 'all', fn ($q) => $q->where('status', $this->status))
            // Oldest first: a queue worked newest-first leaves the shop that
            // has waited longest waiting longest.
            ->orderBy('submitted_at')
            ->get();
    }

    #[Computed]
    public function current(): ?ShopVerification
    {
        return $this->reviewing === null
            ? null
            : ShopVerification::with('tenant')->find($this->reviewing);
    }

    public function open(int $id): void
    {
        $this->reviewing = $id;
        $this->rejectionReason = '';
        $this->error = '';

        $verification = $this->current;

        // Opening a submission claims it, so two operators do not review the
        // same shop and reach different answers.
        if ($verification !== null && $verification->status === VerificationStatus::Submitted) {
            $this->service->beginReview($verification, Auth::user());
        }
    }

    /**
     * A short-lived link to one document.
     *
     * Minted per click rather than rendered into the page: a signed URL in
     * the HTML is a signed URL in the browser history, and every mint is
     * recorded against the operator who asked for it.
     */
    public function documentUrl(string $kind): void
    {
        $verification = $this->current;

        if ($verification === null) {
            return;
        }

        try {
            $this->redirect($this->service->documentUrl($verification, $kind, Auth::user()));
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function approve(): void
    {
        $verification = $this->current;

        if ($verification === null) {
            return;
        }

        $this->service->approve($verification, Auth::user());
        $this->reviewing = null;
        unset($this->queue);
    }

    public function reject(): void
    {
        $verification = $this->current;

        if ($verification === null) {
            return;
        }

        try {
            $this->service->reject($verification, $this->rejectionReason, Auth::user());
            $this->reviewing = null;
            $this->rejectionReason = '';
            $this->error = '';
            unset($this->queue);
        } catch (DomainException $e) {
            $this->error = $e->getMessage();
        }
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Verification</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Shops waiting to be checked. Oldest first.
        </p>
    </div>

    <div class="mb-5 flex flex-wrap gap-2">
        @foreach ([
            'submitted' => 'Awaiting review',
            'under_review' => 'Being reviewed',
            'verified' => 'Verified',
            'rejected' => 'Needs attention',
            'all' => 'All',
        ] as $value => $label)
            <button type="button" wire:click="$set('status', '{{ $value }}')"
                    @class([
                        'rounded-full border px-3 py-1.5 text-xs transition-colors',
                        'border-[var(--color-accent)] text-[var(--color-text)]' => $status === $value,
                        'border-[var(--color-border)] text-[var(--color-muted)] hover:text-[var(--color-text)]' => $status !== $value,
                    ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($error !== '')
        <x-ui.alert tone="negative" class="mb-5">{{ $error }}</x-ui.alert>
    @endif

    @if ($this->queue->isEmpty())
        <x-ui.empty-state
            title="Nothing waiting"
            description="No shop is in this state right now." />
    @else
        <div class="grid gap-5 lg:grid-cols-[22rem_1fr]">
            {{-- ── Queue ─────────────────────────────────────────────── --}}
            <ul class="glass-panel divide-y divide-[var(--color-border)] overflow-hidden">
                @foreach ($this->queue as $item)
                    <li>
                        <button type="button" wire:click="open({{ $item->id }})"
                                @class([
                                    'w-full px-4 py-3 text-left transition-colors',
                                    'bg-[color-mix(in_srgb,var(--color-accent)_14%,transparent)]' => $reviewing === $item->id,
                                    'hover:bg-[var(--color-bg-surface-hover)]' => $reviewing !== $item->id,
                                ])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="truncate text-sm font-medium text-[var(--color-text)]">
                                    {{ $item->tenant?->name ?? 'deleted shop' }}
                                </span>
                                <x-ui.badge :tone="$item->status->tone()">{{ $item->status->label() }}</x-ui.badge>
                            </div>
                            @if ($item->submitted_at)
                                <span class="mt-1 block text-xs text-[var(--color-muted)]">
                                    Submitted {{ $item->submitted_at->diffForHumans() }}
                                </span>
                            @endif
                        </button>
                    </li>
                @endforeach
            </ul>

            {{-- ── Review ────────────────────────────────────────────── --}}
            <div class="glass-panel p-6">
                @if ($this->current === null)
                    <p class="text-sm text-[var(--color-muted)]">Choose a shop to review.</p>
                @else
                    @php $v = $this->current; @endphp

                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-[var(--color-text)]">
                                {{ $v->tenant?->name ?? 'deleted shop' }}
                            </h2>
                            <p class="mt-0.5 text-sm text-[var(--color-muted)]">{{ $v->legal_name }}</p>
                        </div>
                        <x-ui.badge :tone="$v->status->tone()">{{ $v->status->label() }}</x-ui.badge>
                    </div>

                    <dl class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach ([
                            'Trade licence' => $v->trade_licence_no,
                            'BIN / TIN' => $v->bin_tin,
                            'Owner' => $v->owner_name,
                            'Owner ID' => $v->owner_identifier,
                            'Address' => $v->address,
                            'City' => $v->city,
                        ] as $term => $value)
                            <div>
                                <dt class="text-xs text-[var(--color-muted)]">{{ $term }}</dt>
                                {{-- --color-text, never --color-muted: these are
                                     the values being checked against a document. --}}
                                <dd class="mt-0.5 text-sm text-[var(--color-text)]">{{ $value ?: '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <div class="mt-6">
                        <h3 class="text-sm font-medium text-[var(--color-text)]">Documents</h3>
                        <p class="mt-1 text-xs text-[var(--color-muted)]">
                            Opening one is recorded against you and the link lasts five minutes.
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($v->documents() as $kind => $path)
                                @if ($path)
                                    <x-ui.button variant="secondary" size="sm"
                                                 wire:click="documentUrl('{{ $kind }}')">
                                        {{ str_replace('_', ' ', $kind) }}
                                    </x-ui.button>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    @if ($v->status === VerificationStatus::Rejected && $v->rejection_reason)
                        <x-ui.alert tone="negative" title="Previously rejected" class="mt-6">
                            {{ $v->rejection_reason }}
                        </x-ui.alert>
                    @endif

                    @unless ($v->status->isComplete())
                        <div class="mt-8 border-t border-[var(--color-border)] pt-6">
                            <label for="reason" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                                Reason, if rejecting
                            </label>
                            <textarea id="reason" wire:model="rejectionReason" rows="3"
                                      placeholder="Say what is wrong, specifically enough that they can fix it."
                                      class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)] placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] focus:outline-none"></textarea>

                            <div class="mt-4 flex flex-wrap gap-3">
                                <x-ui.button variant="positive" wire:click="approve">Approve</x-ui.button>
                                <x-ui.button variant="danger" wire:click="reject">Reject</x-ui.button>
                            </div>
                        </div>
                    @endunless
                @endif
            </div>
        </div>
    @endif
</div>
