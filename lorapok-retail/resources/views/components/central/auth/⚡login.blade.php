<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new
#[Layout('components.layouts.central')]
class extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public function login(): void
    {
        $this->validate();

        $key = 'central-login|'.mb_strtolower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => $seconds = RateLimiter::availableIn($key),
                    'minutes' => ceil($seconds / 60),
                ]),
            ]);
        }

        if (! Auth::guard('web')->attempt(['email' => $this->email, 'password' => $this->password])) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = Auth::guard('web')->user();

        // Holding a central account is not the same as being allowed to run
        // the platform. Anyone else is signed straight back out.
        if (! $user->isSuperAdmin()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages([
                'email' => __('This account cannot access the admin panel.'),
            ]);
        }

        RateLimiter::clear($key);
        session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        $this->redirectIntended(route('central.shops'), navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-sm animate-fade-slide-up">
        <div class="mb-8 text-center">
            <h1 class="text-2xl font-semibold tracking-tight text-[var(--color-text)]">Lorapok Retail</h1>
            <p class="mt-1 text-sm text-[var(--color-muted)]">Operator sign in</p>
        </div>

        <div class="glass-panel p-6">
            <form wire:submit="login" class="space-y-5">
                <div>
                    <label for="email" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Email</label>
                    <input wire:model="email" id="email" type="email" autocomplete="username" required autofocus
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('email') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-[var(--color-text)]">Password</label>
                    <input wire:model="password" id="password" type="password" autocomplete="current-password" required
                           class="w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-base)]/60 px-3 py-2 text-[var(--color-text)] focus:border-[var(--color-accent)] focus:outline-none">
                    @error('password') <p class="mt-1.5 text-sm text-[var(--color-danger)]">{{ $message }}</p> @enderror
                </div>

                <button type="submit" wire:loading.attr="disabled"
                        class="w-full rounded-[var(--radius)] bg-[var(--color-accent)] px-4 py-2.5 font-medium text-white hover:opacity-90 disabled:opacity-60">
                    <span wire:loading.remove wire:target="login">Sign in</span>
                    <span wire:loading wire:target="login">Signing in…</span>
                </button>
            </form>
        </div>
    </div>
</div>
