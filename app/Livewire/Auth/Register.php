<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Sign-up.
 *
 * Allowed when REGISTRATION_OPEN is on (the default), or when the install has no
 * users yet — so a fresh deploy can always create its first account even if the
 * flag was turned off.
 *
 * WARNING: an account here can read every stored API key, so open sign-up on a
 * public URL is equivalent to leaving the dashboard open. Set
 * REGISTRATION_OPEN=false once the accounts you need exist.
 */
#[Layout('components.layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * True while the install has no account at all — the first-run case.
     *
     * Treated as "first run" if the users table cannot be read at all: on a
     * half-migrated deploy the sign-up page must still work, otherwise a failed
     * migration locks you out of your own install with no way back in.
     */
    public function getPrimeiroUtilizadorProperty(): bool
    {
        try {
            return ! User::query()->exists();
        } catch (Throwable) {
            return true;
        }
    }

    /** Whether sign-up can be completed at all right now. */
    public function getAbertoProperty(): bool
    {
        return $this->registoAberto() || $this->primeiroUtilizador;
    }

    private function registoAberto(): bool
    {
        return (bool) config('contentmachine.auth.registration_open', false);
    }

    public function registar(): void
    {
        if (! $this->aberto) {
            throw ValidationException::withMessages([
                'email' => 'Registration is closed on this install.',
            ]);
        }

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        $utilizador = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        Auth::login($utilizador);
        session()->regenerate();

        $this->redirect(route('painel'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
