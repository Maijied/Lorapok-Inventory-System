<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)]">Start your shop</h1>
        <p class="mt-3 text-[var(--color-muted)]">
            Tell us the name. We set it up on its own address with its own database,
            and email you the sign-in details — usually the same day.
        </p>

        @if ($errors->any())
            <x-ui.alert tone="negative" title="Please check the form" class="mt-6">
                {{ $errors->count() === 1 ? 'One field needs attention.' : $errors->count().' fields need attention.' }}
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('marketing.apply.store') }}" class="mt-8 space-y-5">
            @csrf
            <input type="hidden" name="started_at" value="{{ time() }}">

            <div class="absolute left-[-9999px]" aria-hidden="true">
                <label for="website">Leave this empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <x-ui.field label="Shop name" name="shop_name" :required="true"
                        value="{{ old('shop_name') }}" hint="As your customers know it." />

            <x-ui.field label="Web address" name="slug" :required="true"
                        value="{{ old('slug') }}"
                        hint="Lowercase letters, numbers and hyphens. This becomes yourname.lorapok.tech and cannot be changed later."
                        inputmode="url" autocapitalize="none" spellcheck="false" />

            <x-ui.field label="Your name" name="owner_name" :required="true" value="{{ old('owner_name') }}" autocomplete="name" />
            <x-ui.field label="Email" name="owner_email" type="email" :required="true" value="{{ old('owner_email') }}" autocomplete="email" />
            <x-ui.field label="Phone" name="owner_phone" value="{{ old('owner_phone') }}" autocomplete="tel" hint="Optional." />
            <x-ui.field label="City" name="city" value="{{ old('city') }}" hint="Optional." />

            <x-ui.button type="submit" size="lg" class="w-full">Request my shop</x-ui.button>

            <p class="text-center text-xs text-[var(--color-muted)]">
                No card needed. We will confirm the price before anything is billed.
            </p>
        </form>
    </section>
</x-layouts.marketing>
