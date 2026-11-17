<?php

namespace App\Services\Leads;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * "Convide um colega": an agent with a free (watermarked) video gets a personal link
 * (imocena.com/?convite=<code>). A colleague who arrives by it gets their own free video,
 * as anyone does; once that video is made, the agent's is unlocked clean and emailed.
 *
 * Tracking: the link carries utm_source=convite (channel "convite", see Canal) with the
 * code as utm_content, so visits, leads and purchases are tied to the invite; the lead
 * made through it keeps `convidado_por`; landing_eventos logs each step (tipo convite-*).
 */
class Convites
{
    /** Emails an agent may have us send to colleagues, in all. */
    public const MAX_EMAILS = 10;

    public function __construct(private LeadVideo $videos) {}

    /** The agent's invite code, made the first time it is asked for. */
    public function codigo(object $lead): string
    {
        if (! empty($lead->convite)) {
            return $lead->convite;
        }
        do {
            $codigo = Str::lower(Str::random(8));
        } while (DB::table('leads')->where('convite', $codigo)->exists());
        DB::table('leads')->where('id', $lead->id)->whereNull('convite')->update(['convite' => $codigo, 'updated_at' => now()]);

        return (string) DB::table('leads')->where('id', $lead->id)->value('convite');
    }

    /** The link to share. $meio: how it travels (whatsapp, link, email), kept as utm_medium. */
    public function link(object $lead, string $meio = 'link'): string
    {
        $q = array_filter([
            'convite' => $this->codigo($lead),
            'agencia' => $lead->agencia ?? null,   // a colleague is likely at the same agency: skip the question
            'utm_source' => 'convite',
            'utm_medium' => $meio,
            'utm_campaign' => 'convite-colega',
            'utm_content' => $this->codigo($lead),
        ]);

        return rtrim((string) config('app.url'), '/').'/?'.http_build_query($q);
    }

    /** The lead an invite code belongs to, or null. */
    public function convidante(?string $codigo): ?object
    {
        $codigo = Str::lower(trim((string) $codigo));

        return preg_match('/^[a-z0-9]{6,16}$/', $codigo) ? DB::table('leads')->where('convite', $codigo)->first() : null;
    }

    /** Has this agent still something to unlock (a free video not yet clean)? */
    public static function porDesbloquear(object $lead): bool
    {
        return empty($lead->pago_em) && empty($lead->oferta_em);
    }

    /** The email to a colleague, on the agent's behalf. Replies go to the agent. */
    public function enviarEmail(object $lead, string $emailColega, string $nome): void
    {
        $link = $this->link($lead, 'email');
        $assunto = $nome.' ofereceu-lhe um vídeo do seu imóvel';
        Mail::html(view('emails.convite', ['nome' => $nome, 'link' => $link])->render(), function ($m) use ($emailColega, $assunto, $lead) {
            $m->to($emailColega)->subject($assunto);
            if (! empty($lead->convite_email)) {
                $m->replyTo($lead->convite_email, $lead->nome ?: null);
            }
        });
        DB::table('convites_enviados')->insert(['lead_id' => $lead->id, 'email' => mb_strtolower($emailColega), 'created_at' => now()]);
    }

    /**
     * A lead's video is ready: if it came by an invite, unlock the inviter's video. Not for
     * the agent's own second listing (same agent page or same name), nor twice.
     */
    public function recompensar(object $novo): bool
    {
        if (empty($novo->convidado_por)) {
            return false;
        }
        $quem = DB::table('leads')->find($novo->convidado_por);
        if (! $quem || ! self::porDesbloquear($quem) || $quem->id === $novo->id) {
            return false;
        }
        $mesmo = (! empty($quem->consultor) && $quem->consultor === ($novo->consultor ?? null))
            || (self::nome($quem->nome ?? null) !== '' && self::nome($quem->nome ?? null) === self::nome($novo->nome ?? null));
        if ($mesmo) {
            Log::info('Convite ignorado: mesmo consultor', ['convidante' => $quem->id, 'lead' => $novo->id]);
            self::evento('convite-ignorado', $quem, ['nota' => 'lead '.$novo->id]);

            return false;
        }
        if (! $this->videos->limpar($quem)) {
            Log::warning('Convite: sem clip para limpar', ['convidante' => $quem->id, 'lead' => $novo->id]);

            return false;
        }
        DB::table('leads')->where('id', $quem->id)->update(['oferta_em' => now(), 'updated_at' => now()]);
        self::evento('convite-recompensa', $quem, ['nota' => 'lead '.$novo->id]);
        Log::info('Convite: vídeo limpo desbloqueado', ['convidante' => $quem->id, 'lead' => $novo->id]);

        if (! empty($quem->convite_email)) {
            rescue(fn () => Mail::html(view('emails.convite-oferta', [
                'nome' => $quem->nome,
                'video' => URL::signedRoute('lead.video', ['lead' => $quem->id]),
                'descarregar' => URL::signedRoute('lead.video', ['lead' => $quem->id, 'download' => 1]),
            ])->render(), fn ($m) => $m->to($quem->convite_email)->subject('O seu vídeo sem marca d’água está pronto')), report: true);
        }

        return true;
    }

    /** A name to compare: lower case, single spaces. */
    private static function nome(?string $nome): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $nome)));
    }

    /** One landing_eventos row for the invite funnel. */
    public static function evento(string $tipo, ?object $lead, array $dados = []): void
    {
        rescue(fn () => DB::table('landing_eventos')->insert($dados + [
            'tipo' => $tipo,
            'agencia' => $lead->agencia ?? null,
            'origem' => \App\Services\Marketing\Origem::json(),
            'created_at' => now(),
        ]), report: false);
    }
}
