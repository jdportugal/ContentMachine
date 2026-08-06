<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Sign-up.
 *
 * This app sits on a public URL and holds every API key, so registration is NOT
 * open to the world — that would hand the dashboard to whoever finds it, which
 * is how the keys leaked in the first place. It is allowed when either:
 *
 *   1. the install has no users yet (first-run setup), or
 *   2. the visitor supplies the registration code (REGISTRATION_CODE).
 *
 * With no code configured and an account already present, sign-up is closed.
 */
#[Layout('components.layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $codigo = '';

    /** True while the install has no account at all — the first-run case. */
    public function getPrimeiroUtilizadorProperty(): bool
    {
        return ! User::query()->exists();
    }

    /** Whether sign-up can be completed at all right now. */
    public function getAbertoProperty(): bool
    {
        return $this->primeiroUtilizador || $this->codigoConfigurado() !== '';
    }

    private function codigoConfigurado(): string
    {
        return trim((string) config('contentmachine.auth.registration_code', ''));
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

        // The code is required for every account after the first.
        if (! $this->primeiroUtilizador) {
            $esperado = $this->codigoConfigurado();
            if ($esperado === '' || ! hash_equals($esperado, trim($this->codigo))) {
                throw ValidationException::withMessages([
                    'codigo' => 'That registration code is not valid.',
                ]);
            }
        }

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
