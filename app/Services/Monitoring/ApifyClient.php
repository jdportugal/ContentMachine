<?php

namespace App\Services\Monitoring;

use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Fronteira fina sobre a API da Apify. Corre um actor de forma síncrona e
 * devolve directamente os itens do dataset (run-sync-get-dataset-items).
 * Isolada para permitir Http::fake nos testes. Sem token → excepção clara.
 */
class ApifyClient
{
    /**
     * @param  array<string,mixed>  $input  input do actor
     * @return array<int,array<string,mixed>>  itens do dataset
     */
    public function runActor(string $actorId, array $input, int $timeout = 180): array
    {
        $token = config('services.apify.token');
        if (blank($token)) {
            throw DriverNotConfiguredException::for('apify', 'services.apify.token');
        }

        $base = rtrim((string) config('services.apify.base_url', 'https://api.apify.com'), '/');

        $resposta = Http::timeout($timeout)
            ->acceptJson()
            ->post("{$base}/v2/acts/{$actorId}/run-sync-get-dataset-items?token={$token}", $input)
            ->throw();

        $dados = $resposta->json();

        return is_array($dados) ? $dados : [];
    }
}
