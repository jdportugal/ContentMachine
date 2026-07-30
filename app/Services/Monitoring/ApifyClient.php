<?php

namespace App\Services\Monitoring;

use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Thin boundary over the Apify API. Runs an actor synchronously and
 * returns the dataset items directly (run-sync-get-dataset-items).
 * Isolated to allow Http::fake in tests. No token → clear exception.
 */
class ApifyClient
{
    /**
     * @param  array<string,mixed>  $input  actor input
     * @return array<int,array<string,mixed>>  dataset items
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
