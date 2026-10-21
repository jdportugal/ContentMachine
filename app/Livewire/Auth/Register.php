<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Sign-up. Always available: anyone who reaches /register may create an account.
 *
 * There is deliberately no "registration closed" state — no flag, no invite
 * code, no first-run special case. Sign-up either works or the page does not
 * exist, and it works.
 *
 * WARNING: an account here can read every stored API key, so on a public URL
 * this is equivalent to leaving the dashboard open. Closing it again means
 * putting a gate back in front of this component (or removing the route).
 */
#[Layout('components.layouts.guest')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function registar(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ], [
            'name.required' => 'Indique o seu nome.',
            'email.required' => 'Indique o email.',
            'email.email' => 'Indique um email válido.',
            'email.unique' => 'Já existe uma conta com este email.',
            'password.required' => 'Indique a palavra-passe.',
            'password.min' => 'A palavra-passe tem de ter pelo menos 12 caracteres.',
            'password.confirmed' => 'As palavras-passe não coincidem.',
        ]);

        $utilizador = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        Auth::login($utilizador);
        session()->regenerate();

        $this->redirect(route('imoveis'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
