<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Contas\LinkDeAcesso;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Admin: the customers' accounts and their videos, and credits given by hand to an
 * email (a gift, a refund, a deal outside Stripe). A grant is a purchase row worth 0 €,
 * so it is spent exactly like a bought one; taking credits back marks spare ones used.
 */
#[Layout('components.layouts.app')]
#[Title('Clientes')]
class Clientes extends Component
{
    public string $email = '';

    public int $quantidade = 1;

    public string $nota = '';

    public bool $enviarLink = true;

    public string $procura = '';

    public function adicionar(LinkDeAcesso $links): void
    {
        $this->email = Str::lower(trim($this->email));
        $this->validate([
            'email' => 'required|email|max:190',
            'quantidade' => 'required|integer|min:1|max:200',
            'nota' => 'nullable|string|max:200',
        ], [
            'email.required' => 'Escreva o email do cliente.',
            'email.email' => 'Este email não parece válido.',
            'quantidade.min' => 'Pelo menos 1 vídeo.',
            'quantidade.max' => 'No máximo 200 de cada vez.',
        ]);
        $existente = User::query()->where('email', $this->email)->first();
        if ($existente?->isAdmin()) {
            $this->addError('email', 'Este email é de um administrador: os vídeos de cliente não se aplicam.');

            return;
        }
        $user = $existente ?? $links->contaPara($this->email);
        DB::table('compras')->insert([
            'sessao' => 'admin-'.Str::uuid(),
            'payment_link' => 'admin',
            'user_id' => $user->id,
            'email' => $user->email,
            'nome' => $user->name,
            'total' => 0,
            'videos' => $this->quantidade,
            'videos_usados' => 0,
            'dados' => json_encode(['origem' => 'admin', 'por' => Auth::user()->email, 'nota' => trim($this->nota)], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($this->enviarLink) {
            rescue(fn () => $links->enviar($user, 'Tem '.$user->creditos().' '.($user->creditos() === 1 ? 'vídeo' : 'vídeos').' na sua conta Imocena',
                'Juntámos '.$this->quantidade.' '.($this->quantidade === 1 ? 'vídeo' : 'vídeos').' à sua conta. Entre e cole o link de um anúncio para o fazer.'), report: true);
        }
        session()->flash('feito', ($existente ? '' : 'Conta criada. ').$this->quantidade.' '.($this->quantidade === 1 ? 'vídeo adicionado' : 'vídeos adicionados').' a '.$user->email.' (tem agora '.$user->creditos().').');
        $this->procura = $user->email;
        $this->reset('email', 'quantidade', 'nota');
    }

    /** Take $n of a customer's spare credits back (marked used, oldest first). */
    public function retirar(int $userId, int $n = 1): void
    {
        $user = User::query()->find($userId);
        if (! $user || $user->isAdmin()) {
            return;
        }
        $falta = min($n, $user->creditos());
        foreach (DB::table('compras')->where('user_id', $user->id)->whereColumn('videos_usados', '<', 'videos')->orderBy('id')->get() as $c) {
            if ($falta <= 0) {
                break;
            }
            $tirar = min($falta, $c->videos - $c->videos_usados);
            DB::table('compras')->where('id', $c->id)->update(['videos_usados' => $c->videos_usados + $tirar, 'updated_at' => now()]);
            $falta -= $tirar;
        }
        session()->flash('feito', 'Retirado. '.$user->email.' tem agora '.$user->creditos().'.');
    }

    public function render()
    {
        $procura = Str::lower(trim($this->procura));
        $clientes = User::query()->where('is_admin', false)
            ->when($procura !== '', fn ($q) => $q->where('email', 'like', '%'.$procura.'%'))
            ->orderByDesc('id')->limit(100)->get()
            ->map(function (User $u) {
                $compras = DB::table('compras')->where('user_id', $u->id);

                return (object) [
                    'id' => $u->id,
                    'email' => $u->email,
                    'nome' => $u->name,
                    'creditos' => $u->creditos(),
                    'videos' => DB::table('leads')->where('user_id', $u->id)->whereNotNull('pago_em')->count(),
                    'pago' => (int) (clone $compras)->sum('total'),
                    'oferecidos' => (int) (clone $compras)->where('payment_link', 'admin')->sum('videos'),
                    'desde' => $u->created_at,
                    // First touch: the first purchase's, else their first video's.
                    'origem' => \App\Services\Marketing\Origem::resumo((clone $compras)->whereNotNull('origem')->orderBy('id')->value('origem')
                        ?? DB::table('leads')->where('user_id', $u->id)->whereNotNull('origem')->orderBy('id')->value('origem')),
                ];
            });

        return view('livewire.clientes', ['clientes' => $clientes]);
    }
}
