<?php

namespace App\Services\News;

use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Real driver (integration point): collects content from the sources (Apify/Reddit/
 * YouTube) and synthesizes a report with a model (Gemini/OpenAI). Requires keys.
 */
class ApiNewsDriver implements NewsDriver
{
    public function relatorio(array $fontes): array
    {
        $token = config('services.apify.token');

        if (blank($token)) {
            throw DriverNotConfiguredException::for('news', 'services.apify.token');
        }

        // Integration point: 1) collect items per source via Apify/APIs;
        // 2) send to the synthesis model; 3) return in the contract format.
        $itens = Http::withToken($token)->throw()->timeout(30)
            ->post(config('services.apify.base_url', 'https://api.apify.com'), ['fontes' => $fontes])
            ->json();

        return $itens;
    }
}
