<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-6xl px-4 pb-10 pt-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)] sm:text-4xl">Pricing</h1>
        <p class="mt-3 max-w-2xl text-[var(--color-muted)]">
            One shop, one price. Every plan includes your own subdomain, your own
            database and unlimited sales.
        </p>
    </section>

    <section class="mx-auto max-w-6xl px-4 pb-16 sm:px-6 lg:px-8">
        @if ($plans->isEmpty())
            {{-- Rendered from the plans table, so an empty table is an honest
                 empty state rather than hardcoded prices that have drifted. --}}
            <x-ui.empty-state
                title="Pricing is being finalised"
                description="Get in touch and we will tell you what it costs for your shop.">
                <x-slot:action>
                    <x-ui.button :href="route('marketing.contact')">Ask us</x-ui.button>
                </x-slot:action>
            </x-ui.empty-state>
        @else
            <div class="grid gap-5 md:grid-cols-{{ min($plans->count(), 3) }}">
                @foreach ($plans as $i => $plan)
                    <div class="glass-panel animate-fade-slide-up stagger-{{ min($i + 1, 4) }} flex flex-col p-6">
                        <h2 class="text-lg font-semibold text-[var(--color-text)]">{{ $plan->name }}</h2>

                        @if ($plan->description)
                            <p class="mt-1.5 text-sm text-[var(--color-muted)]">{{ $plan->description }}</p>
                        @endif

                        <p class="mt-5 flex items-baseline gap-1.5">
                            <span class="tabular text-3xl font-semibold text-[var(--color-text)] font-[family-name:var(--font-mono)]">
                                {{ $plan->isFree() ? 'Free' : \App\Support\Money::ofMinor($plan->price_minor, $plan->currency)->format('৳') }}
                            </span>
                            @unless ($plan->isFree())
                                <span class="text-sm text-[var(--color-muted)]">/ {{ $plan->interval }}</span>
                            @endunless
                        </p>

                        @if ($plan->trial_days > 0)
                            <p class="mt-1 text-xs text-[var(--color-muted)]">{{ $plan->trial_days }}-day trial</p>
                        @endif

                        <ul class="mt-6 flex-1 space-y-2.5 text-sm">
                            @foreach ($plan->limits as $limit)
                                <li class="flex items-baseline gap-2">
                                    <span aria-hidden="true" class="text-[var(--r-positive)]">✓</span>
                                    <span class="text-[var(--color-text)]">
                                        {{-- null means unlimited, deliberately: a sentinel
                                             like -1 eventually gets compared with < and
                                             silently caps the plan. --}}
                                        {{ $limit->isUnlimited() ? 'Unlimited' : number_format($limit->value) }}
                                        <span class="text-[var(--color-muted)]">{{ str_replace('_', ' ', $limit->key) }}</span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>

                        <x-ui.button
                            :href="route('marketing.apply', ['plan' => $plan->slug])"
                            :variant="$i === 0 ? 'primary' : 'secondary'"
                            class="mt-6 w-full">
                            Start with {{ $plan->name }}
                        </x-ui.button>
                    </div>
                @endforeach
            </div>

            <p class="mt-8 text-sm text-[var(--color-muted)]">
                Paid by bKash, Nagad or bank transfer. We invoice you directly — there is
                no card on file and nothing renews without you knowing.
            </p>
        @endif
    </section>
</x-layouts.marketing>
