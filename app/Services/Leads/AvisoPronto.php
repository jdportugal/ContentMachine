<?php

namespace App\Services\Leads;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * "O vídeo do seu imóvel está pronto": one email per free video, to the address the agent left
 * on the form (email_video) or, failing that, the one they gave for alerts (email). It brings
 * them back if they left the page: the video, the launch offer (clean version for 10 €), the
 * pack, and their invite link. Sent once (aviso_pronto_em); never for a failed video.
 */
class AvisoPronto
{
    public function __construct(private Convites $convites) {}

    public static function destino(object $lead): ?string
    {
        $email = trim((string) ($lead->email_video ?? '')) ?: trim((string) ($lead->email ?? ''));

        return $email !== '' ? $email : null;
    }

    /** A Stripe Payment Link carrying this lead, or null when the link is not configured. */
    public static function stripe(string $plano, object $lead): ?string
    {
        $link = (string) config("contentmachine.stripe.links.$plano");

        return $link === '' ? null : $link.'?'.http_build_query(['client_reference_id' => 'lead-'.$lead->id, 'locale' => 'pt']);
    }

    public function enviar(object $lead): bool
    {
        if (! empty($lead->aviso_pronto_em) || empty($lead->pronto_em) || ! empty($lead->falhou_em) || ! ($destino = self::destino($lead))) {
            return false;
        }
        $limpo = ! empty($lead->pago_em) || ! empty($lead->oferta_em);
        if (! $limpo && empty($lead->convite_email)) {
            // The invite's reward goes to the same address.
            DB::table('leads')->where('id', $lead->id)->update(['convite_email' => $destino]);
            $lead = DB::table('leads')->find($lead->id);
        }

        $html = view('emails.video-pronto', [
            'nome' => trim(strtok((string) ($lead->nome ?? ''), ' ') ?: ''),
            'video' => URL::signedRoute('lead.video', ['lead' => $lead->id]),
            'descarregar' => URL::signedRoute('lead.video', ['lead' => $lead->id, 'download' => 1]),
            'limpo' => $limpo,
            'oferta' => $limpo ? null : self::stripe('primeiro', $lead),
            'pack' => $limpo ? null : self::stripe('pack10', $lead),
            'convite' => $limpo ? null : $this->convites->link($lead, 'email-pronto'),
        ])->render();
        Mail::html($html, fn ($m) => $m->to($destino)->subject('O vídeo do seu imóvel está pronto'));

        DB::table('leads')->where('id', $lead->id)->update(['aviso_pronto_em' => now(), 'updated_at' => now()]);
        Convites::evento('video-pronto-email', $lead);

        return true;
    }
}
