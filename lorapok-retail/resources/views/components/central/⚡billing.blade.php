<?php

use App\Domain\Billing\BillingService;
use App\Domain\Billing\InvalidTransition;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Layout('components.layouts.central')]
class extends Component
{
    #[Url(except: '')]
    public string $shop = '';

    public string $planSlug = '';

    public string $paymentReference = '';

    public string $voidReason = '';

    public ?int $voiding = null;

    public string $error = '';

    public string $notice = '';

    #[Computed]
    public function billing(): BillingService
    {
        return app(BillingService::class);
    }

    #[Computed]
    public function shops()
    {
        return Tenant::query()->orderBy('name')->get();
    }

    #[Computed]
    public function tenant(): ?Tenant
    {
        return $this->shop === '' ? null : Tenant::where('slug', $this->shop)->first();
    }

    #[Computed]
    public function subscription(): ?Subscription
    {
        $tenant = $this->tenant;

        return $tenant === null
            ? null
            : Subscription::with('plan')->where('tenant_id', $tenant->id)->latest('id')->first();
    }

    #[Computed]
    public function invoices()
    {
        $tenant = $this->tenant;

        return $tenant === null
            ? collect()
            : SubscriptionInvoice::with('attempts')
                ->where('tenant_id', $tenant->id)
                ->orderByDesc('id')
                ->get();
    }

    #[Computed]
    public function plans()
    {
        return Plan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    /**
     * Shops with an overdue invoice, so the operator has somewhere to start.
     *
     * Surfaced rather than requiring someone to check shop by shop — an
     * overdue invoice nobody looks at is an invoice that never gets chased.
     */
    #[Computed]
    public function overdue()
    {
        return SubscriptionInvoice::with('tenant')
            ->where('status', InvoiceStatus::Open)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->get();
    }

    private function reset_(): void
    {
        unset($this->subscription, $this->invoices, $this->overdue);
        $this->error = '';
    }

    public function subscribe(): void
    {
        $tenant = $this->tenant;
        $plan = Plan::where('slug', $this->planSlug)->first();

        if ($tenant === null || $plan === null) {
            $this->error = 'Choose a shop and a plan.';

            return;
        }

        $this->billing->subscribe($tenant, $plan, Auth::user());
        $this->reset_();
        $this->notice = "{$tenant->name} is on {$plan->name}, trialing.";
    }

    public function issue(): void
    {
        $subscription = $this->subscription;

        if ($subscription === null) {
            $this->error = 'This shop has no subscription to invoice.';

            return;
        }

        $invoice = $this->billing->issueInvoice($subscription, Auth::user());
        $this->reset_();
        $this->notice = "Invoice {$invoice->number} issued.";
    }

    /**
     * Record that the shop says it paid, then confirm it.
     *
     * Two steps in one action deliberately: the operator pressing this IS the
     * confirmation. The separation matters in the domain — a declared payment
     * is not money received — and it would be theatre to make one person
     * click twice.
     */
    public function markPaid(int $invoiceId): void
    {
        $invoice = SubscriptionInvoice::find($invoiceId);

        if ($invoice === null) {
            return;
        }

        try {
            $attempt = $this->billing->declarePayment($invoice, [
                'reference' => $this->paymentReference ?: null,
                'method' => 'manual',
            ]);

            $this->billing->settle($invoice, $attempt, Auth::user());
            $this->paymentReference = '';
            $this->reset_();
            $this->notice = "Invoice {$invoice->number} marked paid.";
        } catch (InvalidTransition $e) {
            $this->error = $e->getMessage();
        }
    }

    public function void(): void
    {
        $invoice = SubscriptionInvoice::find($this->voiding);

        if ($invoice === null) {
            return;
        }

        try {
            $this->billing->void($invoice, $this->voidReason, Auth::user());
            $this->voiding = null;
            $this->voidReason = '';
            $this->reset_();
            $this->notice = "Invoice {$invoice->number} voided.";
        } catch (InvalidTransition $e) {
            $this->error = $e->getMessage();
        }
    }

    public function move(string $status): void
    {
        $subscription = $this->subscription;

        if ($subscription === null) {
            return;
        }

        try {
            $this->billing->transition(
                $subscription,
                SubscriptionStatus::from($status),
                Auth::user(),
                'changed from the billing screen',
            );
            $this->reset_();
            $this->notice = 'Subscription is now '.SubscriptionStatus::from($status)->label().'.';
        } catch (InvalidTransition $e) {
            // Shown rather than swallowed: the operator asked for something
            // the state machine refuses, and the reason is worth reading.
            $this->error = $e->getMessage();
        }
    }

    public function money(int $minor, string $currency = 'BDT'): string
    {
        return Money::ofMinor($minor, $currency)->format('৳');
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6">
        <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Billing</h1>
        <p class="mt-1 text-sm text-[var(--color-muted)]">
            Shops pay by bKash, Nagad or bank transfer. You confirm the money arrived.
        </p>
    </div>

    @if ($error !== '')
        <x-ui.alert tone="negative" class="mb-5">{{ $error }}</x-ui.alert>
    @endif

    @if ($notice !== '')
        <x-ui.alert tone="positive" class="mb-5">{{ $notice }}</x-ui.alert>
    @endif

    {{-- ── Overdue, first ────────────────────────────────────────────── --}}
    @if ($this->overdue->isNotEmpty())
        <div class="glass-panel mb-6 p-5">
            <h2 class="text-sm font-semibold text-[var(--color-text)]">Overdue</h2>
            <p class="mt-1 text-xs text-[var(--color-muted)]">
                Talk to them before suspending. A shop that cannot sell cannot pay.
            </p>

            <ul class="mt-3 divide-y divide-[var(--color-border)]">
                @foreach ($this->overdue as $invoice)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-2.5">
                        <button type="button" wire:click="$set('shop', '{{ $invoice->tenant?->slug }}')"
                                class="text-sm text-[var(--color-text)] hover:text-[var(--color-accent)]">
                            {{ $invoice->tenant?->name ?? 'deleted shop' }}
                        </button>
                        <span class="flex items-center gap-3 text-sm">
                            <span class="tabular text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                                {{ $this->money($invoice->amount_minor, $invoice->currency) }}
                            </span>
                            <x-ui.badge tone="attention">
                                {{ $invoice->due_at?->diffForHumans() }}
                            </x-ui.badge>
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ── Pick a shop ───────────────────────────────────────────────── --}}
    <div class="glass-panel mb-6 p-4">
        <label class="block">
            <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">Shop</span>
            <select wire:model.live="shop"
                    class="w-full max-w-sm rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)]">
                <option value="">Choose a shop</option>
                @foreach ($this->shops as $s)
                    <option value="{{ $s->slug }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($this->tenant !== null)
        @php $subscription = $this->subscription; @endphp

        <div class="glass-panel p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-[var(--color-text)]">{{ $this->tenant->name }}</h2>
                    @if ($subscription)
                        <p class="mt-0.5 text-sm text-[var(--color-muted)]">
                            {{ $subscription->plan?->name }} ·
                            {{ $this->money($subscription->plan?->price_minor ?? 0) }} / {{ $subscription->plan?->interval }}
                        </p>
                    @endif
                </div>

                @if ($subscription)
                    <x-ui.badge :tone="$subscription->status->tone()">{{ $subscription->status->label() }}</x-ui.badge>
                @endif
            </div>

            @if ($subscription === null)
                <div class="mt-6">
                    <p class="text-sm text-[var(--color-muted)]">This shop is not on a plan yet.</p>
                    <div class="mt-3 flex flex-wrap items-end gap-3">
                        <label>
                            <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">Plan</span>
                            <select wire:model="planSlug"
                                    class="rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)]">
                                <option value="">Choose</option>
                                @foreach ($this->plans as $plan)
                                    <option value="{{ $plan->slug }}">{{ $plan->name }} — {{ $this->money($plan->price_minor) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-ui.button wire:click="subscribe">Start a trial</x-ui.button>
                    </div>
                </div>
            @else
                {{-- ── Status ────────────────────────────────────────── --}}
                <div class="mt-6 border-t border-[var(--color-border)] pt-5">
                    <h3 class="text-sm font-medium text-[var(--color-text)]">Status</h3>

                    @if ($subscription->status->allowedNext() === [])
                        {{-- A heading over an empty row reads as a broken
                             screen. Cancelled is terminal, so say that
                             instead of showing nothing. --}}
                        <p class="mt-1 text-xs text-[var(--color-muted)]">
                            This subscription has ended. Starting again means a new one,
                            not reviving this.
                        </p>
                    @else
                        <p class="mt-1 text-xs text-[var(--color-muted)]">
                            Suspension is the only status that stops a shop selling.
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            {{-- Only transitions the state machine actually
                                 allows are offered, so the screen cannot ask
                                 for something that will be refused. --}}
                            @foreach ($subscription->status->allowedNext() as $next)
                                <x-ui.button
                                    size="sm"
                                    :variant="$next === App\Enums\SubscriptionStatus::Suspended ? 'danger' : 'secondary'"
                                    wire:click="move('{{ $next->value }}')">
                                    {{ $next->label() }}
                                </x-ui.button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- ── Invoices ──────────────────────────────────────── --}}
                <div class="mt-6 border-t border-[var(--color-border)] pt-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="text-sm font-medium text-[var(--color-text)]">Invoices</h3>
                        <x-ui.button size="sm" wire:click="issue">Issue an invoice</x-ui.button>
                    </div>

                    @if ($this->invoices->isEmpty())
                        <p class="mt-3 text-sm text-[var(--color-muted)]">None yet.</p>
                    @else
                        <ul class="mt-3 divide-y divide-[var(--color-border)]">
                            @foreach ($this->invoices as $invoice)
                                <li class="py-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <span class="flex items-center gap-2.5">
                                            <span class="tabular text-sm text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                                                {{ $invoice->number }}
                                            </span>
                                            <x-ui.badge :tone="$invoice->status->tone()">{{ $invoice->status->label() }}</x-ui.badge>
                                            @if ($invoice->isOverdue())
                                                <x-ui.badge tone="attention">overdue</x-ui.badge>
                                            @endif
                                        </span>

                                        <span class="flex items-center gap-3">
                                            <span class="tabular text-sm text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                                                {{ $this->money($invoice->amount_minor, $invoice->currency) }}
                                            </span>

                                            @if ($invoice->status === App\Enums\InvoiceStatus::Open)
                                                <x-ui.button size="sm" variant="positive"
                                                             wire:click="markPaid({{ $invoice->id }})">
                                                    Mark paid
                                                </x-ui.button>
                                                <x-ui.button size="sm" variant="ghost"
                                                             wire:click="$set('voiding', {{ $invoice->id }})">
                                                    Void
                                                </x-ui.button>
                                            @endif
                                        </span>
                                    </div>

                                    @if ($invoice->paid_at)
                                        <p class="mt-1 text-xs text-[var(--color-muted)]">
                                            Paid {{ $invoice->paid_at->format('j M Y') }}
                                            @if ($invoice->gateway_ref)
                                                · ref <span class="font-[family-name:var(--font-mono)]">{{ $invoice->gateway_ref }}</span>
                                            @endif
                                        </p>
                                    @endif

                                    @if ($invoice->status === App\Enums\InvoiceStatus::Void && $invoice->note)
                                        {{-- Voided invoices stay visible with
                                             their reason. An invoice that can
                                             disappear is not a record. --}}
                                        <p class="mt-1 text-xs text-[var(--color-muted)]">Voided: {{ $invoice->note }}</p>
                                    @endif

                                    @if ($voiding === $invoice->id)
                                        <div class="mt-3 flex flex-wrap items-end gap-3">
                                            <label class="flex-1 min-w-[14rem]">
                                                <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">Why</span>
                                                <input type="text" wire:model="voidReason"
                                                       class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)]">
                                            </label>
                                            <x-ui.button size="sm" variant="danger" wire:click="void">Confirm void</x-ui.button>
                                            <x-ui.button size="sm" variant="ghost" wire:click="$set('voiding', null)">Cancel</x-ui.button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        <label class="mt-4 block max-w-sm">
                            <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">
                                Payment reference
                            </span>
                            <input type="text" wire:model="paymentReference"
                                   placeholder="bKash transaction id, bank slip number"
                                   class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)] placeholder:text-[var(--color-muted)]">
                            <span class="mt-1 block text-xs text-[var(--color-muted)]">
                                Recorded against the payment, so a disputed one can be traced.
                            </span>
                        </label>
                    @endif
                </div>
            @endif
        </div>
    @endif
</div>
