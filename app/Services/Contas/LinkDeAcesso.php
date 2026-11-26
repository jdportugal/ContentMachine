<?php

namespace App\Services\Contas;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Passwordless accounts: a buyer's account is made from their checkout email, and
 * they come back with a signed link sent to that email (valid 30 minutes).
 */
class LinkDeAcesso
{
    public const MINUTOS = 30;

    /** The account for this email (its own, or the one it is shared into), made if needed — always a customer, never an admin. */
    public function contaPara(string $email, ?string $nome = null): User
    {
        $email = Str::lower(trim($email));
        $user = User::porEmail($email);
        if ($user) {
            return $user;
        }

        return User::create([
            'name' => trim((string) $nome) !== '' ? trim((string) $nome) : Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make(Str::random(48)),   // never used: they sign in by link
        ]);
    }

    /** $para: a page of the site to land on (a path, part of the signature); else their account. */
    public function url(User $user, ?string $para = null, ?int $minutos = null): string
    {
        return URL::temporarySignedRoute('entrar.link', now()->addMinutes($minutos ?? self::MINUTOS), ['user' => $user->id] + ($para ? ['para' => $para] : []));
    }

    /**
     * Email the sign-in link. Never throws: a mail failure must not break a payment or a form.
     * $destino: the address it goes to when not the account's own (an email the account is shared with).
     */
    public function enviar(User $user, ?string $assunto = null, ?string $intro = null, ?string $para = null, ?int $minutos = null, ?string $destino = null): void
    {
        $link = $this->url($user, $para, $minutos);
        $assunto ??= 'O seu link de acesso à Imocena';
        $intro ??= 'Clique no botão para entrar na sua conta Imocena. O link é válido durante '.self::MINUTOS.' minutos.';
        try {
            Mail::html(view('emails.link-acesso', ['link' => $link, 'intro' => $intro, 'nome' => $user->name])->render(), function ($m) use ($user, $assunto, $destino) {
                $m->to($destino ?? $user->email)->subject($assunto);
            });
        } catch (\Throwable $e) {
            Log::warning('Link de acesso não enviado', ['user' => $user->id, 'erro' => $e->getMessage()]);
        }
    }

    public function entrar(User $user): void
    {
        Auth::login($user, remember: true);
        session()->regenerate();
        if (! $user->email_verified_at) {   // reaching their inbox (or paying with it) proves the email
            $user->forceFill(['email_verified_at' => now()])->save();
        }
    }

    /** The leads this account owns, newest first. */
    public static function leads(User $user)
    {
        return DB::table('leads')->where('user_id', $user->id)->orderByDesc('id')->get();
    }
}
