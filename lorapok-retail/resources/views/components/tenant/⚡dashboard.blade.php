<?php

use App\Models\Tenant\Setting;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    public function logout(): void
    {
        Auth::guard('tenant')->logout();
        session()->invalidate();
        session()->regenerateToken();

        $this->redirect(route('tenant.login'), navigate: true);
    }

    public function with(): array
    {
        return [
            'user' => Auth::guard('tenant')->user(),
            'currency' => Setting::get('locale.currency', 'BDT'),
        ];
    }
};
?>

<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-wrap items-center justify-between gap-4 animate-fade-slide-up">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">
                {{ tenant('name') }}
            </h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                Signed in as {{ $user->name }}
                <span class="text-[var(--color-accent)]">
                    ({{ $user->getRoleNames()->map(fn ($r) => str_replace('_', ' ', $r))->join(', ') ?: 'no role' }})
                </span>
            </p>
        </div>

        <button wire:click="logout"
                class="rounded-[var(--radius)] border border-[var(--color-border)] px-4 py-2 text-sm text-[var(--color-muted)] transition-colors hover:text-[var(--color-text)]">
            Sign out
        </button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['Products', '—', 'Catalogue not built yet'],
            ['Today’s sales', $currency . ' —', 'POS lands in a later phase'],
            ['Stock value', $currency . ' —', 'Computed from the ledger'],
        ] as $i => [$label, $value, $hint])
            <div class="glass-panel p-6 animate-fade-slide-up stagger-{{ $i + 1 }}">
                <p class="text-sm text-[var(--color-muted)]">{{ $label }}</p>
                {{-- Real figures are never rendered as a fake zero; an unknown
                     value shows an em dash until the module exists. --}}
                <p class="mt-2 text-2xl font-semibold tabular text-[var(--color-text)]
                          font-[family-name:var(--font-mono)]">{{ $value }}</p>
                <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="glass-panel mt-6 p-6 animate-fade-slide-up stagger-4">
        <h2 class="text-sm font-medium text-[var(--color-text)]">Your permissions</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @forelse ($user->getAllPermissions()->pluck('name')->sort() as $permission)
                <span class="rounded-full border border-[var(--color-border)] bg-white/5 px-2.5 py-1 text-xs text-[var(--color-muted)]">
                    {{ str_replace('_', ' ', $permission) }}
                </span>
            @empty
                <span class="text-sm text-[var(--color-muted)]">No permissions assigned.</span>
            @endforelse
        </div>
    </div>
</div>
