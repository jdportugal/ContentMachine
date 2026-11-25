<?php

namespace App\Services\Auth;

use App\Mail\EmailImocena;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Signing in by email, without a password: a random token in the cache names the account and where to go
 * after, works once (pulled on use) and expires. The token is the whole secret, so it is long and only ever
 * travels in the email.
 */
class LinkEntrada
{
    /** The sign-in URL for this account, landing on $para (a path on this site). */
    public function url(User $user, string $para, int $minutos = 30): string
    {
        $token = Str::random(48);
        Cache::put('entrar:'.$token, ['user' => $user->id, 'para' => $para], now()->addMinutes($minutos));

        return route('login.link', $token);
    }

    /** Emails a sign-in link (30 minutes, once) — to $email when it is one the account is shared with. */
    public function enviar(User $user, string $para, ?string $email = null): void
    {
        Mail::to($email ?? $user->email)->send(new EmailImocena(
            assunto: 'O seu link para entrar na Imocena',
            titulo: 'Entrar na sua conta',
            texto: 'Carregue no botão para entrar e ver os seus imóveis: o modelo 3D, as fotografias e o vídeo.',
            botao: 'Entrar na Imocena',
            url: $this->url($user, $para),
            nota: 'O link vale 30 minutos e só funciona uma vez. Se não pediu para entrar, ignore este email: ninguém entra na sua conta sem ele.',
        ));
    }

    /**
     * The account and destination a token stands for, once; null when unknown, used or expired.
     *
     * @return array{0: User, 1: string}|null
     */
    public function usar(string $token): ?array
    {
        $dados = Cache::pull('entrar:'.$token);
        $user = $dados ? User::find($dados['user']) : null;

        return $user ? [$user, str_starts_with($dados['para'], '/') && ! str_starts_with($dados['para'], '//') ? $dados['para'] : '/'] : null;
    }
}
