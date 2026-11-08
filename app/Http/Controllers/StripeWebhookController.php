<?php

namespace App\Http\Controllers;

use App\Services\Pagamentos\Compras;
use App\Services\Pagamentos\StripeWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/** POST /stripe/webhook — checkout.session.completed from the Payment Links. */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, Compras $compras): Response
    {
        try {
            $event = (new StripeWebhook((string) config('contentmachine.stripe.webhook_secret')))
                ->event($request->getContent(), (string) $request->header('Stripe-Signature', ''));
        } catch (RuntimeException $e) {
            Log::warning('Stripe webhook rejeitado', ['erro' => $e->getMessage()]);

            return response('invalid signature', 400);
        }

        if (in_array($event['type'], ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $compras->registar((array) ($event['data']['object'] ?? []));
        }

        return response('ok', 200);
    }
}
