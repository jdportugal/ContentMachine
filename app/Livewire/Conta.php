<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The account's settings, for every signed-in user. For now: the other emails that share the account — each
 * signs in to this same account (its videos, properties and credits), as if it were the account's own address.
 */
#[Layout('components.layouts.app')]
#[Title('Definições da conta')]
class Conta extends Component
{
    public const MAX_EMAILS = 10;

    public string $email = '';

    public function adicionar(): void
    {
        $this->email = Str::lower(trim($this->email));
        $user = auth()->user();
        $this->validate([
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email', 'unique:user_emails,email',
                fn ($a, $v, $fail) => count($user->emailsPartilhados()) >= self::MAX_EMAILS ? $fail('No máximo '.self::MAX_EMAILS.' emails por conta.') : null],
        ], [
            'email.required' => 'Indique o email a adicionar.',
            'email.email' => 'Indique um email válido.',
            'email.unique' => 'Este email já entra noutra conta (ou nesta). Cada email só pode entrar numa conta.',
        ]);
        DB::table('user_emails')->insert(['user_id' => $user->id, 'email' => $this->email, 'created_at' => now(), 'updated_at' => now()]);
        $this->reset('email');
        $this->dispatch('toast', message: 'Email adicionado: já pode entrar nesta conta.');
    }

    public function remover(string $email): void
    {
        DB::table('user_emails')->where('user_id', auth()->id())->where('email', $email)->delete();
    }

    public function render()
    {
        return view('livewire.conta', ['emails' => auth()->user()->emailsPartilhados()]);
    }
}
