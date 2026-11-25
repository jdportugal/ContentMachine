<?php

namespace App\Livewire\Auth;

use App\Models\Imoplanta;
use App\Models\User;
use App\Services\Auth\LinkEntrada;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The sign-in screen, for admins and customers alike. By default it emails a one-time sign-in link
 * (LinkEntrada) and says the same whether or not the address has an account, so it can't be used to find
 * out who is a customer. The password form stays one click away, for admins (their account is created by the
 * deploy, EnsureAdminUser) and for when email isn't working.
 */
#[Layout('components.layouts.site')]
#[Title('Imocena · Entrar')]
class Login extends Component
{
    public string $email = '';

    public bool $comPalavraPasse = false;

    /** The address the link went to: the page then shows «Veja o seu email». */
    public ?string $enviadoPara = null;

    public ?string $aviso = null;

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

        $this->redirectIntended(default: route('imoveis'), navigate: false);
    }

    public function mount(): void
    {
        $this->aviso = session('aviso');
    }

    public function enviarLink(LinkEntrada $links): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate(['email' => ['required', 'email:rfc', 'max:255']], [
            'email.required' => 'Indique o seu email.',
            'email.email' => 'Indique um email válido.',
        ]);
        $chave = 'login-link:'.request()->ip();
        if (RateLimiter::tooManyAttempts($chave, 10)) {
            $this->addError('email', 'Pediu muitos links. Tente novamente daqui a uma hora.');

            return;
        }
        RateLimiter::hit($chave, 3600);

        $user = User::porEmail($this->email);
        if (! $user) {   // a new address: its account, with no credits of any kind, and straight in
            $user = User::create(['name' => Str::before($this->email, '@'), 'email' => $this->email, 'password' => Str::random(40)]);
            Auth::login($user, true);
            session()->regenerate();
            $this->redirectIntended(default: route('imoveis'), navigate: false);

            return;
        }
        $para = session('url.intended') ?? ($user->can('imoplanta') && Imoplanta::where('user_id', $user->id)->exists() ? route('imoplanta') : route('imoveis'));
        $links->enviar($user, parse_url($para, PHP_URL_PATH) ?: '/', $this->email);
        $this->enviadoPara = $this->email;
    }

    public function outroEmail(): void
    {
        $this->reset('email', 'enviadoPara', 'aviso');
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
