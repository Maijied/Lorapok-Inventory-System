<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[Layout('components.layouts.tenant')]
class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();

        // The system this replaces overrode the login controller and dropped
        // Laravel's throttling with it, leaving passwords open to unlimited
        // guessing. Rate limiting is keyed on email + IP so one attacker
        // cannot lock out every account from a single address.
        $this->ensureIsNotRateLimited();

        if (! Auth::guard('tenant')->attempt(
            ['email' => $this->email, 'password' => $this->password],
            $this->remember,
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                // Deliberately does not reveal whether the address exists.
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::guard('tenant')->user();

        // Deactivated staff keep their history but must not get in.
        if (! $user->isActive()) {
            Auth::guard('tenant')->logout();

            throw ValidationException::withMessages([
                'email' => __('This account has been deactivated.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();

        $this->redirectIntended(route('tenant.dashboard'), navigate: true);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        // Namespaced by tenant so one shop's failed logins never throttle
        // another shop's staff.
        return 'login|'.tenant('id').'|'.mb_strtolower($this->email).'|'.request()->ip();
    }
};
?>

<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm animate-fade-slide-up">

        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">
                {{ tenant('name') }}
            </h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Sign in to continue</p>
        </div>

        <div class="glass-panel p-6">
            <form wire:submit="login" class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                        Email
                    </label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        autocomplete="username"
                        required
                        autofocus
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] placeholder:text-[var(--color-muted)] focus:border-[var(--color-accent)] focus:outline-none"
                    >
                    @error('email')
                        <p id="email-error" class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">
                        Password
                    </label>
                    <input
                        wire:model="password"
                        id="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none"
                    >
                    @error('password')
                        <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-[var(--color-muted)]">
                    <input wire:model="remember" type="checkbox"
                           class="rounded border-[var(--color-border)] bg-transparent text-[var(--color-accent)]">
                    Remember me
                </label>

                <button
                    type="submit"
                    class="w-full rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2.5 font-medium text-white transition-opacity hover:opacity-90 disabled:opacity-60"
                    wire:loading.attr="disabled"
                >
                    <span wire:loading.remove wire:target="login">Sign in</span>
                    <span wire:loading wire:target="login">Signing in…</span>
                </button>
            </form>
        </div>

        <p class="mt-6 text-center text-xs text-[var(--color-muted)]">
            Powered by <a href="https://lorapok.tech" class="underline hover:text-[var(--color-text)]">Lorapok Labs</a>
        </p>
    </div>
</div>
