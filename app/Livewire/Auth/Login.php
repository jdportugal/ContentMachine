<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\Contas\LinkDeAcesso;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The sign-in screen. Customers (buyers) sign in with a link emailed to them — no
 * password; the operator signs in with the admin password (EnsureAdminUser).
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
        ], [
            'email.required' => 'Indique o email.',
            'email.email' => 'Indique um email válido.',
            'password.required' => 'Indique a palavra-passe.',
        ]);

        $this->garantirNaoBloqueado();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->chaveThrottle(), self::BLOQUEIO);

            throw ValidationException::withMessages([
                'email' => 'Email ou palavra-passe incorretos.',
            ]);
        }

        RateLimiter::clear($this->chaveThrottle());
        // New session id on privilege change — otherwise a session id captured
        // before login stays valid afterwards (session fixation).
        session()->regenerate();

        $this->redirectIntended(default: route(Auth::user()->isAdmin() ? 'imoveis' : 'conta'), navigate: false);
    }

    /** True once a sign-in link was asked for (the same message whether or not the email has an account). */
    public bool $linkEnviado = false;

    /** Passwordless: email a sign-in link to a customer's account. */
    public function enviarLink(LinkDeAcesso $contas): void
    {
        $this->validate(['email' => ['required', 'string', 'email']], [
            'email.required' => 'Indique o email com que comprou.',
            'email.email' => 'Indique um email válido.',
        ]);
        $chave = 'login-link:'.Str::lower($this->email).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, 3)) {
            throw ValidationException::withMessages(['email' => 'Já enviámos links há pouco. Veja o seu email ou tente daqui a '.RateLimiter::availableIn($chave).' segundos.']);
        }
        RateLimiter::hit($chave, 600);

        $user = User::query()->where('email', Str::lower(trim($this->email)))->first();
        if ($user && ! $user->isAdmin()) {
            $contas->enviar($user);
        }
        $this->linkEnviado = true;
    }

    private function garantirNaoBloqueado(): void
    {
        if (! RateLimiter::tooManyAttempts($this->chaveThrottle(), self::TENTATIVAS)) {
            return;
        }

        throw ValidationException::withMessages([
            'email' => 'Demasiadas tentativas. Tente novamente daqui a '.RateLimiter::availableIn($this->chaveThrottle()).' segundos.',
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
