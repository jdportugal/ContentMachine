<?php

namespace App\Services\Billing;

use App\Models\Imoplanta;
use App\Models\User;
use App\Services\Imoplanta\Imoplantas;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Stripe Checkout over its REST API: start a payment for a plan, and turn a paid
 * Checkout Session into a purchase (`compras`) — once, whether the webhook or the
 * customer's return to the site gets there first.
 */
class Stripe
{
    private const API = 'https://api.stripe.com/v1';

    /** How old a webhook signature may be (Stripe's own default). */
    private const TOLERANCIA = 300;

    /**
     * A plan by its key, from either catalogue: the video plans or Imoplanta's
     * property packs. `unidades` is what the purchase holds (videos or properties).
     *
     * @return array{nome:string,preco:int,unidades:int,tipo:string}|null
     */
    public function plano(string $plano): ?array
    {
        if (is_array($p = config('contentmachine.planos')[$plano] ?? null)) {
            return ['nome' => $p['nome'], 'preco' => $p['preco'], 'unidades' => $p['videos'], 'tipo' => 'videos'];
        }
        if (is_array($p = config('contentmachine.imoplanta.planos')[$plano] ?? null)) {
            return ['nome' => $p['nome'], 'preco' => $p['preco'], 'unidades' => $p['imoveis'], 'tipo' => 'imoplanta'];
        }
        // a step-4 add-on of one property ('extra_planta', 'extra_video'): bought and spent at once
        if (str_starts_with($plano, 'extra_') && is_array($p = config('contentmachine.imoplanta.extras')[substr($plano, 6)] ?? null)) {
            return ['nome' => $p['nome'], 'preco' => $p['preco'], 'unidades' => 1, 'tipo' => 'imoplanta_extra'];
        }

        return null;
    }

    /**
     * The Checkout page URL for this user buying this plan. With a property saved before payment (the public
     * plans page), paying starts it (cumprir) and the customer comes back to that page.
     */
    public function checkout(User $user, string $plano, ?Imoplanta $imovel = null): string
    {
        $p = $this->plano($plano);
        $volta = match (true) {
            $p['tipo'] === 'imoplanta_extra' => route('imoplanta.imovel', $imovel->id),   // back to its add-ons
            (bool) $imovel => route('plantas'),
            $p['tipo'] === 'imoplanta' => route('imoplanta'),
            default => route('imoveis'),
        };
        $unidade = $p['tipo'] === 'imoplanta' ? ['imóvel', 'imóveis'] : ['vídeo', 'vídeos'];
        $nome = $p['tipo'] === 'imoplanta_extra' ? 'Imocena · '.$p['nome'].' · '.$imovel->nome
            : 'Imocena · '.$p['nome'].' ('.$p['unidades'].' '.$unidade[$p['unidades'] === 1 ? 0 : 1].')';

        return Http::withToken((string) config('services.stripe.secret'))->asForm()
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'locale' => 'pt',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'eur',
                        'unit_amount' => $p['preco'],
                        'product_data' => ['name' => $nome],
                    ],
                ]],
                'customer_email' => $user->email,
                'client_reference_id' => (string) $user->id,
                'metadata' => ['plano' => $plano] + ($imovel ? ['imoplanta' => (string) $imovel->id] : []),
                'tax_id_collection' => ['enabled' => 'true'],   // the NIF, for the invoice
                'success_url' => $volta.'?pago={CHECKOUT_SESSION_ID}',
                'cancel_url' => $volta,
            ])->throw()->json('url');
    }

    public function sessao(string $id): array
    {
        return Http::withToken((string) config('services.stripe.secret'))
            ->get(self::API.'/checkout/sessions/'.rawurlencode($id))->throw()->json();
    }

    /**
     * Records the purchase a paid session stands for. Idempotent on the session id.
     * Returns false when there is nothing (yet) to record: unpaid (a Multibanco
     * reference not paid yet), or a session that isn't one of ours.
     */
    public function cumprir(array $s): bool
    {
        $p = $this->plano((string) ($s['metadata']['plano'] ?? ''));
        $user = User::find($s['client_reference_id'] ?? null);
        if (($s['payment_status'] ?? '') !== 'paid' || ! $p || ! $user
            || ($s['amount_total'] ?? null) !== $p['preco'] || ($s['currency'] ?? '') !== 'eur') {
            return false;
        }

        $novo = DB::table('compras')->insertOrIgnore([
            'user_id' => $user->id,
            'plano' => $s['metadata']['plano'],
            'tipo' => $p['tipo'],
            'videos' => $p['unidades'],     // units bought: videos, or Imoplanta properties (an add-on: spent at once)
            'restantes' => $p['tipo'] === 'imoplanta_extra' ? 0 : $p['unidades'],
            'valor' => $p['preco'],
            'stripe_sessao' => $s['id'],
            'stripe_pagamento' => $s['payment_intent'] ?? null,
            'nif' => $s['customer_details']['tax_ids'][0]['value'] ?? null,
            'expira_em' => now()->addMonths((int) config('contentmachine.planos_validade_meses')),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Paid for a property's add-on: start it, once (only the first fulfilment of this session).
        if ($p['tipo'] === 'imoplanta_extra') {
            $imovel = Imoplanta::where('user_id', $user->id)->find($s['metadata']['imoplanta'] ?? null);
            if ($novo && $imovel) {
                app(Imoplantas::class)->iniciarExtra($imovel, substr($s['metadata']['plano'], 6));
            }

            return true;
        }
        // Paid for a property saved before payment: start it (once — iniciar checks it is still waiting).
        $imovel = Imoplanta::where('user_id', $user->id)->where('estado', Imoplanta::A_PAGAR)->find($s['metadata']['imoplanta'] ?? null);
        if ($imovel) {
            app(Imoplantas::class)->iniciar($imovel);
        }

        return true;
    }

    /** A fully refunded charge takes back the videos (or properties) its purchase has left. */
    public function reembolsado(array $charge): void
    {
        if (! empty($charge['refunded']) && ! empty($charge['payment_intent'])) {
            DB::table('compras')->where('stripe_pagamento', $charge['payment_intent'])
                ->update(['restantes' => 0, 'reembolsado_em' => now(), 'updated_at' => now()]);
        }
    }

    /** Checks the Stripe-Signature header: HMAC-SHA256 of "<t>.<payload>", recent. */
    public function assinaturaValida(string $payload, string $cabecalho): bool
    {
        $segredo = (string) config('services.stripe.webhook_secret');
        $t = null;
        $assinaturas = [];
        foreach (explode(',', $cabecalho) as $parte) {
            [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
            $k === 't' ? $t = $v : ($k === 'v1' ? $assinaturas[] = $v : null);
        }
        if ($segredo === '' || ! ctype_digit((string) $t) || abs(time() - (int) $t) > self::TOLERANCIA) {
            return false;
        }
        $esperada = hash_hmac('sha256', $t.'.'.$payload, $segredo);

        return collect($assinaturas)->contains(fn ($a) => hash_equals($esperada, $a));
    }
}
