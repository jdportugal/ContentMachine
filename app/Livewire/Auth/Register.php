<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Sign-up for customers: anyone may create an account, which buys videos and
 * sees only its own (the `admin` gate keeps the studio and its API keys to the
 * accounts with is_admin — never set here). A guest sent here from a price card
 * goes back to that checkout afterwards (the intended URL).
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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', 'unique:user_emails,email'],
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

        $this->redirectIntended(default: route('imoveis'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
