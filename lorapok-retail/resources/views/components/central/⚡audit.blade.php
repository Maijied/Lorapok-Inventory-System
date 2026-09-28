<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new
#[Layout('components.layouts.central')]
class extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $action = '';

    #[Url(except: '')]
    public string $shop = '';

    #[Url(except: '')]
    public string $since = '';

    /**
     * The audit trail.
     *
     * This table has been written to since Phase 1 and read by nobody — which
     * makes it a log, not an audit. A record only counts as one if someone can
     * actually go and look.
     *
     * Read through the query builder rather than a model: entries outlive the
     * shops they describe (a deleted shop's rows stay), so there is nothing
     * reliable to hydrate a relationship from.
     */
    #[Computed]
    public function entries()
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('central_audit_logs as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.actor_id')
            ->leftJoin('tenants as t', 't.id', '=', 'a.tenant_id')
            ->when($this->action !== '', fn ($q) => $q->where('a.action', 'like', $this->action.'%'))
            ->when($this->shop !== '', fn ($q) => $q->where('t.slug', $this->shop))
            ->when($this->since !== '', fn ($q) => $q->whereDate('a.created_at', '>=', $this->since))
            ->orderByDesc('a.created_at')
            ->select([
                'a.id', 'a.action', 'a.subject_type', 'a.subject_id',
                'a.before', 'a.after', 'a.ip', 'a.created_at',
                'u.name as actor_name', 'u.email as actor_email',
                't.slug as shop_slug', 't.name as shop_name',
                // Kept so a deleted shop's entries are still attributable.
                'a.tenant_id',
            ])
            ->paginate(40);
    }

    /** The action prefixes actually present, so the filter offers real options. */
    #[Computed]
    public function actionGroups(): array
    {
        return DB::connection(config('tenancy.database.central_connection'))
            ->table('central_audit_logs')
            ->selectRaw("SUBSTRING_INDEX(action, '.', 1) as prefix")
            ->distinct()
            ->orderBy('prefix')
            ->pluck('prefix')
            ->all();
    }

    #[Computed]
    public function shops()
    {
        return Tenant::query()->orderBy('name')->get(['slug', 'name']);
    }

    public function clearFilters(): void
    {
        $this->reset(['action', 'shop', 'since']);
        $this->resetPage();
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    /** Which semantic tone an action reads as. */
    public function toneFor(string $action): string
    {
        return match (true) {
            str_contains($action, 'suspend'), str_contains($action, 'deleted'),
            str_contains($action, 'rejected'), str_contains($action, 'blocked') => 'negative',
            str_contains($action, 'impersonation') => 'attention',
            str_contains($action, 'paid'), str_contains($action, 'restored'),
            str_contains($action, 'active') => 'positive',
            default => 'neutral',
        };
    }
};
?>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Audit</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">
                Everything operators have done to a shop, newest first.
            </p>
        </div>
    </div>

    {{-- ── Filters ───────────────────────────────────────────────────── --}}
    <div class="glass-panel mb-5 flex flex-wrap items-end gap-4 p-4">
        <label class="flex-1 min-w-[10rem]">
            <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">Action</span>
            <select wire:model.live="action"
                    class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)]">
                <option value="">All</option>
                @foreach ($this->actionGroups as $prefix)
                    <option value="{{ $prefix }}">{{ $prefix }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex-1 min-w-[10rem]">
            <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">Shop</span>
            <select wire:model.live="shop"
                    class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)]">
                <option value="">All</option>
                @foreach ($this->shops as $s)
                    <option value="{{ $s->slug }}">{{ $s->name }}</option>
                @endforeach
            </select>
        </label>

        <label class="flex-1 min-w-[9rem]">
            <span class="mb-1.5 block text-xs font-medium text-[var(--color-text)]">Since</span>
            <input type="date" wire:model.live="since"
                   class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-sm text-[var(--color-text)]">
        </label>

        @if ($action !== '' || $shop !== '' || $since !== '')
            <x-ui.button variant="ghost" size="sm" wire:click="clearFilters">Clear</x-ui.button>
        @endif
    </div>

    {{-- ── Entries ───────────────────────────────────────────────────── --}}
    @if ($this->entries->isEmpty())
        <x-ui.empty-state
            title="Nothing matches"
            description="No operator action has been recorded for these filters." />
    @else
        <div class="glass-panel overflow-hidden">
            <ul class="divide-y divide-[var(--color-border)]">
                @foreach ($this->entries as $entry)
                    <li class="p-4">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge :tone="$this->toneFor($entry->action)">{{ $entry->action }}</x-ui.badge>

                                @if ($entry->shop_name)
                                    <span class="text-sm text-[var(--color-text)]">{{ $entry->shop_name }}</span>
                                @elseif ($entry->tenant_id)
                                    {{-- The shop is gone but its history is not. --}}
                                    <span class="text-sm text-[var(--color-muted)]">deleted shop</span>
                                @endif
                            </div>

                            {{-- Timestamps use --color-text, not --color-muted:
                                 in an audit trail the time is the evidence. --}}
                            <time datetime="{{ $entry->created_at }}"
                                  class="tabular text-xs text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                                {{ \Illuminate\Support\Carbon::parse($entry->created_at)->format('j M Y H:i') }}
                            </time>
                        </div>

                        <p class="mt-1.5 text-sm text-[var(--color-muted)]">
                            {{ $entry->actor_name ?? 'system' }}
                            @if ($entry->actor_email)
                                <span class="opacity-70">({{ $entry->actor_email }})</span>
                            @endif
                            @if ($entry->ip)
                                · <span class="font-[family-name:var(--font-mono)]">{{ $entry->ip }}</span>
                            @endif
                        </p>

                        @php
                            $after = $entry->after ? json_decode($entry->after, true) : null;
                            $reason = is_array($after) ? ($after['reason'] ?? null) : null;
                        @endphp

                        @if ($reason)
                            {{-- Surfaced rather than buried in the JSON: on an
                                 impersonation entry this is the whole point of
                                 the record. --}}
                            <p class="mt-1.5 text-sm text-[var(--color-text)]">“{{ $reason }}”</p>
                        @endif

                        @if ($entry->before || $entry->after)
                            <details class="mt-2">
                                <summary class="cursor-pointer text-xs text-[var(--color-muted)] hover:text-[var(--color-text)]">
                                    Details
                                </summary>
                                <pre class="mt-2 overflow-x-auto rounded-[var(--radius)] bg-[var(--color-bg-base)]/60 p-3 text-xs text-[var(--color-muted)]">{{ json_encode(['before' => json_decode((string) $entry->before, true), 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-5">{{ $this->entries->links() }}</div>
    @endif
</div>
