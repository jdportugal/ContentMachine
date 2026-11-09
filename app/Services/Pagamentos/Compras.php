<?php

namespace App\Services\Pagamentos;

use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Store\ClipRecord;
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
    public function __construct(private LeadVideo $videos) {}

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

        $compraId = DB::table('compras')->insertGetId([
            'sessao' => $id,
            'payment_link' => $session['payment_link'] ?? null,
            'email' => $detalhes['email'] ?? ($session['customer_email'] ?? null),
            'nome' => $detalhes['name'] ?? null,
            'nif' => $nif,
            'total' => (int) ($session['amount_total'] ?? 0),
            'moeda' => strtolower((string) ($session['currency'] ?? 'eur')),
            'videos' => (int) ($meta['videos'] ?? 0),
            'lead_id' => $leadId,
            'dados' => json_encode($session, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($leadId && (int) ($meta['videos'] ?? 0) === 1) {
            $this->entregarVideoLimpo($compraId, $leadId);
        }

        Log::info('Compra registada', ['compra' => $compraId, 'sessao' => $id, 'videos' => $meta['videos'] ?? null, 'lead' => $leadId, 'total' => $session['amount_total'] ?? null]);

        return DB::table('compras')->find($compraId);
    }

    /** "lead-12" (what the landing passes as client_reference_id) → 12. */
    public static function leadId(string $ref): ?int
    {
        return preg_match('/^lead-(\d+)$/', $ref, $m) ? (int) $m[1] : null;
    }

    /** Re-render the lead's clip without the watermark; the lead's link then serves the clean file. */
    private function entregarVideoLimpo(int $compraId, int $leadId): void
    {
        $lead = $this->videos->lead($leadId);
        $clip = $lead ? $this->videos->clip($lead) : null;   // activates the lead's project
        if (! $clip) {
            Log::warning('Compra sem clip para entregar', ['compra' => $compraId, 'lead' => $leadId]);

            return;
        }
        $clip->update(['meta' => ['free_lead' => false] + $clip->meta, 'status' => ClipRecord::STATUS_RENDERING]);
        RenderJob::dispatch($clip->id);

        DB::table('leads')->where('id', $leadId)->update(['pago_em' => now(), 'updated_at' => now()]);
        DB::table('compras')->where('id', $compraId)->update(['videos_usados' => 1, 'entregue_em' => now(), 'updated_at' => now()]);
    }
}
