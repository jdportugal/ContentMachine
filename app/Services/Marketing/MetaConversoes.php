<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;

/**
 * Meta's Conversions API: the same Lead and Purchase the browser pixel sends, from the
 * server, with the same event id so Meta counts each once. Recovers what ad blockers and
 * iOS hide. Only with the visitor's consent, and only when the token is configured.
 */
class MetaConversoes
{
    public function ativo(): bool
    {
        return (string) config('contentmachine.meta_pixel') !== '' && (string) config('contentmachine.meta_capi.token') !== '';
    }

    /**
     * @param  array<string,mixed>  $dados  custom_data (value, currency…)
     * @param  array<string,mixed>|null  $origem  the visitor's first touch (for the click id)
     */
    public function enviar(string $evento, string $id, array $dados = [], ?string $email = null, ?array $origem = null): bool
    {
        if (! $this->ativo() || ! Consentimento::medicao()) {
            return false;
        }
        $r = request();
        $utilizador = array_filter([
            'client_ip_address' => $r->ip(),
            'client_user_agent' => (string) $r->userAgent(),
            'fbc' => $r->cookie('_fbc') ?: ($origem['fbc'] ?? null),
            'fbp' => $r->cookie('_fbp') ?: null,
            'em' => $email ? [hash('sha256', mb_strtolower(trim($email)))] : null,
        ]);
        $evento = array_filter([
            'event_name' => $evento,
            'event_time' => time(),
            'event_id' => $id,
            'action_source' => 'website',
            'event_source_url' => $r->fullUrl(),
            'user_data' => $utilizador,
            'custom_data' => $dados ?: null,
        ]);
        $corpo = ['data' => [$evento], 'access_token' => (string) config('contentmachine.meta_capi.token')];
        if ($teste = (string) config('contentmachine.meta_capi.test_code')) {
            $corpo['test_event_code'] = $teste;
        }

        return (bool) rescue(function () use ($corpo) {
            $versao = (string) config('contentmachine.meta_capi.version', 'v23.0');
            $resposta = Http::timeout(4)->asJson()->post('https://graph.facebook.com/'.$versao.'/'.config('contentmachine.meta_pixel').'/events', $corpo);
            if ($resposta->failed()) {
                report(new \RuntimeException('Meta CAPI '.$resposta->status().': '.mb_substr($resposta->body(), 0, 300)));
            }

            return $resposta->successful();
        }, false);
    }
}
