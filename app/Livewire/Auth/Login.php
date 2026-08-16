<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The sign-in screen. Single-tenant: there is no registration and no password
 * reset by email — the admin account is created by the deploy (EnsureAdminUser)
 * and its credentials are changed from Settings once you are in.
 */
#[Layout('components.layouts.guest')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /** Brute force is the whole threat model for a login exposed to the internet. */
    private const TENTATIVAS = 5;

    private const BLOQUEIO = 60; // seconds

    public function entrar(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->garantirNaoBloqueado();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->chaveThrottle(), self::BLOQUEIO);

            throw ValidationException::withMessages([
                'email' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->chaveThrottle());
        // New session id on privilege change — otherwise a session id captured
        // before login stays valid afterwards (session fixation).
        session()->regenerate();

        $this->redirectIntended(default: route('painel'), navigate: false);
    }

    private function garantirNaoBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chaveThrottle(), self::TENTATIVAS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Too many attempts. Try again in '.RateLimiter::availableIn($this->chaveThrottle()).' seconds.',
        ]);
    }

    private function chaveThrottle(): string
    {
        return 'login:'.Str::lower($this->email).'|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
