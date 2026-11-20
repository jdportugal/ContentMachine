<?php

namespace App\Services\Leads;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Two days after a free video, an agent who has done nothing since (no purchase, no other
 * video, no clean copy earned) gets one reminder of the invite: send your link to a colleague
 * and, once they make their free video, yours comes without the watermark. A promotional
 * email, so only to agents who agreed to be contacted (marketing_em) and have not opted out;
 * it carries the one-click unsubscribe. Sent once per agent (lembrete_convite_em).
 */
class LembreteConvite
{
    /** How long after the video is ready. */
    public const DEPOIS_HORAS = 48;

    public function __construct(private Convites $convites) {}

    /** @return Collection<int, object> the leads due a reminder now */
    public function devidos(): Collection
    {
        $candidatos = DB::table('leads')
            ->whereNotNull('pronto_em')->where('pronto_em', '<=', now()->subHours(self::DEPOIS_HORAS))
            ->whereNull('falhou_em')->whereNull('pago_em')->whereNull('oferta_em')->whereNull('lembrete_convite_em')
            ->whereNotNull('marketing_em')->whereNull('marketing_cancelado_em')
            ->whereNotNull('email')->where('email', '!=', '')
            ->where(fn ($q) => $q->whereNull('origem')->orWhere('origem', 'not like', '%teste-interno%'))
            ->orderBy('id')->get();

        return $candidatos->filter(fn ($l) => ! $this->fezMais($l))->values();
    }

    /** The same agent asked for another video, bought something, or was already reminded on another listing. */
    private function fezMais(object $lead): bool
    {
        $emails = array_values(array_unique(array_filter(array_map(fn ($e) => mb_strtolower(trim((string) $e)), [$lead->email ?? null, $lead->email_video ?? null]))));
        $mesmo = DB::table('leads')->where('id', '!=', $lead->id)->where(function ($q) use ($lead, $emails) {
            if ($emails !== []) {
                $q->orWhereIn(DB::raw('lower(email)'), $emails)->orWhereIn(DB::raw('lower(email_video)'), $emails);
            }
            if (! empty($lead->consultor)) {
                $q->orWhere('consultor', $lead->consultor);
            }
            if (! empty($lead->user_id)) {
                $q->orWhere('user_id', $lead->user_id);
            }
        });
        // Any other lead of theirs: a later video means they came back; an earlier one already had its own reminder.
        if (($emails !== [] || ! empty($lead->consultor) || ! empty($lead->user_id)) && $mesmo->exists()) {
            return true;
        }

        return $emails !== [] && DB::table('compras')->where('total', '>', 0)->whereIn(DB::raw('lower(email)'), $emails)->exists();
    }

    public function enviar(object $lead): bool
    {
        $destino = trim((string) $lead->email);
        if ($destino === '' || ! empty($lead->lembrete_convite_em)) {
            return false;
        }
        if (empty($lead->convite_email)) {
            // The clean video is emailed to this address when a colleague makes theirs.
            DB::table('leads')->where('id', $lead->id)->update(['convite_email' => $destino]);
            $lead = DB::table('leads')->find($lead->id);
        }
        $link = $this->convites->link($lead, 'whatsapp');
        $mensagem = 'Olá! Faço os vídeos dos meus imóveis na Imocena: cola-se o link do anúncio e o vídeo fica pronto em minutos, com narração. O primeiro é grátis, experimenta: '.$link;
        $cancelar = URL::signedRoute('lead.emails.cancelar', ['lead' => $lead->id]);

        $html = view('emails.convite-lembrete', [
            'nome' => trim(strtok((string) ($lead->nome ?? ''), ' ') ?: ''),
            'video' => URL::signedRoute('lead.video', ['lead' => $lead->id]),
            'whatsapp' => 'https://wa.me/?text='.rawurlencode($mensagem),
            'link' => $this->convites->link($lead, 'email-lembrete'),
            'linkCurto' => preg_replace('#^https?://#', '', rtrim((string) config('app.url'), '/')).'/video-gratis?convite='.$this->convites->codigo($lead),
            'oferta' => AvisoPronto::stripe('primeiro', $lead),
            'cancelar' => $cancelar,
        ])->render();
        Mail::html($html, function ($m) use ($destino, $cancelar) {
            $m->to($destino)->subject('O seu vídeo pode ficar sem marca d’água, sem pagar');
            $m->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$cancelar.'>');
            $m->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        });

        DB::table('leads')->where('id', $lead->id)->update(['lembrete_convite_em' => now(), 'updated_at' => now()]);
        Convites::evento('convite-lembrete', $lead);

        return true;
    }
}
