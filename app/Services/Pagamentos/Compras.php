<?php

namespace App\Services\Pagamentos;

use App\Services\Contas\LinkDeAcesso;
use App\Services\Leads\LeadVideo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * What a paid Stripe Checkout session becomes: a `compras` row (idempotent on the
 * session id) and, for a single video bought for a free-video lead, the lead's
 * reel rendered again without the watermark — same clip, same /v/{lead} link.
 * Packs are recorded with their credits; spending them is the consultant's account.
 */
class Compras
{
    public function __construct(private LeadVideo $videos, private LinkDeAcesso $contas) {}

    /** @param  array<string,mixed>  $session  a Stripe checkout.session object */
    public function registar(array $session): ?object
    {
        if (($session['payment_status'] ?? '') !== 'paid') {
            return null;
        }
        $id = (string) ($session['id'] ?? '');
        if ($id === '' || DB::table('compras')->where('sessao', $id)->exists()) {
            return DB::table('compras')->where('sessao', $id)->first();
        }

        $meta = (array) ($session['metadata'] ?? []);
        $detalhes = (array) ($session['customer_details'] ?? []);
        // NIF: the checkout's custom field "NIF / VAT (para a fatura)" first, else a tax ID Stripe collected.
        $campoNif = collect((array) ($session['custom_fields'] ?? []))
            ->first(fn ($f) => str_contains(strtolower((string) data_get($f, 'label.custom', data_get($f, 'key', ''))), 'nif'));
        $nif = data_get($campoNif, 'text.value') ?? collect((array) ($detalhes['tax_ids'] ?? []))->pluck('value')->first();
        $nif = $nif !== null ? trim((string) $nif) : null;
        $leadId = self::leadId((string) ($session['client_reference_id'] ?? ''));
        $imoplantaId = self::imoplantaId((string) ($session['client_reference_id'] ?? ''));

        // The buyer's account: their checkout email IS the login (passwordless).
        $email = $detalhes['email'] ?? ($session['customer_email'] ?? null);
        $user = $email ? $this->contas->contaPara((string) $email, $detalhes['name'] ?? null) : null;

        $compraId = DB::table('compras')->insertGetId([
            'user_id' => $user?->id,
            'sessao' => $id,
            'payment_link' => $session['payment_link'] ?? null,
            'email' => $detalhes['email'] ?? ($session['customer_email'] ?? null),
            'nome' => $detalhes['name'] ?? null,
            'nif' => $nif,
            'total' => (int) ($session['amount_total'] ?? 0),
            'moeda' => strtolower((string) ($session['currency'] ?? 'eur')),
            // one balance of video credits: an Imoplanta property's link (50 €) brings its 5 credits, an old AI video link
            // its 3; the property / AI columns keep what was bought, already counted as spent
            'videos' => (int) ($meta['videos'] ?? 0) + (int) ($meta['imoveis'] ?? 0) * Creditos::IMOPLANTA
                + (int) ($meta['videos_ia'] ?? 0) * Creditos::VIDEO_IA_ANTIGO,
            'imoveis' => (int) ($meta['imoveis'] ?? 0), 'imoveis_usados' => (int) ($meta['imoveis'] ?? 0),
            'videos_ia' => (int) ($meta['videos_ia'] ?? 0), 'videos_ia_usados' => (int) ($meta['videos_ia'] ?? 0),
            'lead_id' => $leadId,
            'imoplanta_id' => $imoplantaId,
            'extra' => in_array($meta['extra'] ?? null, ['planta', 'video', 'ambos'], true) ? $meta['extra'] : null,
            'dados' => json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($leadId && $user) {   // the free video they paid from is theirs now
            DB::table('leads')->where('id', $leadId)->whereNull('user_id')->update(['user_id' => $user->id]);
        }
        if ($leadId && (int) ($meta['videos'] ?? 0) === 1) {
            $this->entregarVideoLimpo($compraId, $leadId);
        }
        // Imoplanta: the property it was bought for starts (a property credit), or its add-on does.
        if ($user && ($imoplantaId || (int) ($meta['imoveis'] ?? 0) > 0)) {
            app(\App\Services\Imoplanta\Imoplantas::class)->pago(DB::table('compras')->find($compraId), $user);
        }
        if ($user) {
            $this->contas->enviar($user, 'Pagamento recebido — a sua conta Imocena',
                'Obrigado pela compra. Os seus vídeos e créditos ficam na sua conta Imocena: entre com o botão abaixo (sem palavra-passe). O link é válido durante '.LinkDeAcesso::MINUTOS.' minutos; depois peça outro em imocena.com/login.');
        }

        Log::info('Compra registada', ['compra' => $compraId, 'sessao' => $id, 'videos' => $meta['videos'] ?? null, 'lead' => $leadId, 'total' => $session['amount_total'] ?? null]);

        return DB::table('compras')->find($compraId);
    }

    /** "imoplanta-7" (what Imoplanta's pages pass as client_reference_id) → 7. */
    public static function imoplantaId(string $ref): ?int
    {
        return preg_match('/^imoplanta-(\d+)$/', $ref, $m) ? (int) $m[1] : null;
    }

    /** "ferramenta-7" (what the Ferramentas IA pages pass as client_reference_id) → 7. */
    public static function ferramentaId(string $ref): ?int
    {
        return preg_match('/^ferramenta-(\d+)$/', $ref, $m) ? (int) $m[1] : null;
    }

    /** "lead-12" (what the landing passes as client_reference_id) → 12. */
    public static function leadId(string $ref): ?int
    {
        return preg_match('/^lead-(\d+)$/', $ref, $m) ? (int) $m[1] : null;
    }

    /**
     * The lead's clip, clean. Usually instant: the clean copy was rendered right after the
     * free one (RenderJob $limpo). If it is still rendering, it is handed over when it lands;
     * if there is none (older videos), the clip is re-rendered without the watermark.
     */
    public function entregarVideoLimpo(int $compraId, int $leadId): void
    {
        $lead = $this->videos->lead($leadId);
        if (! $lead || ! $this->videos->limpar($lead)) {
            Log::warning('Compra sem clip para entregar', ['compra' => $compraId, 'lead' => $leadId]);

            return;
        }

        DB::table('leads')->where('id', $leadId)->update(['pago_em' => now(), 'updated_at' => now()]);
        DB::table('compras')->where('id', $compraId)->update(['videos_usados' => DB::raw('videos_usados + 1'), 'entregue_em' => now(), 'updated_at' => now()]);
    }
}
