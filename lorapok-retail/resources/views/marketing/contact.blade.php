<x-layouts.marketing :seo="$seo">
    <section class="mx-auto max-w-xl px-4 py-16 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-semibold tracking-tight text-[var(--color-text)]">Contact</h1>
        <p class="mt-3 text-[var(--color-muted)]">
            Questions about pricing, or moving a shop you already run onto this.
            Bangla or English, either is fine.
        </p>

        @if ($errors->any())
            <x-ui.alert tone="negative" title="Please check the form" class="mt-6">
                {{ $errors->count() === 1 ? 'One field needs attention.' : $errors->count().' fields need attention.' }}
            </x-ui.alert>
        @endif

        <form method="POST" action="{{ route('marketing.contact.store') }}" class="mt-8 space-y-5">
            @csrf

            {{-- Records when the page was rendered. A form completed in under
                 three seconds was not read by a person. --}}
            <input type="hidden" name="started_at" value="{{ time() }}">

            {{-- Honeypot: off-screen rather than display:none, which some bots
                 check for, and excluded from the tab order and the
                 accessibility tree so nobody using a keyboard or a screen
                 reader can land in it by accident. --}}
            <div class="absolute left-[-9999px]" aria-hidden="true">
                <label for="website">Leave this empty</label>
                <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <x-ui.field label="Your name" name="name" :required="true" value="{{ old('name') }}" autocomplete="name" />
            <x-ui.field label="Email" name="email" type="email" :required="true" value="{{ old('email') }}" autocomplete="email" />
            <x-ui.field label="Phone" name="phone" hint="Optional — often faster than email." value="{{ old('phone') }}" autocomplete="tel" />

            <div>
                <label for="message" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                    Message
                    <span class="text-[var(--r-negative)]" aria-hidden="true">*</span>
                    <span class="sr-only">(required)</span>
                </label>
                <textarea id="message" name="message" rows="6" required
                          @error('message') aria-invalid="true" aria-describedby="message-error" @enderror
                          class="w-full rounded-[var(--radius)] border bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] focus:outline-none {{ $errors->has('message') ? 'border-[var(--r-negative)]' : 'border-[var(--color-border)]' }}">{{ old('message') }}</textarea>
                @error('message')
                    <p id="message-error" class="mt-1.5 text-sm text-[var(--r-negative)]">{{ $message }}</p>
                @enderror
            </div>

            <x-ui.button type="submit" size="lg" class="w-full">Send</x-ui.button>
        </form>
    </section>
</x-layouts.marketing>
