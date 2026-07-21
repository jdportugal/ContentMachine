<?php

namespace App\Services\News;

use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Driver real (ponto de integração): recolhe conteúdo das fontes (Apify/Reddit/
 * YouTube) e sintetiza um relatório com um modelo (Gemini/OpenAI). Requer chaves.
 */
class ApiNewsDriver implements NewsDriver
{
    public function relatorio(array $fontes): array
    {
        $token = config('services.apify.token');

        if (blank($token)) {
            throw DriverNotConfiguredException::for('news', 'services.apify.token');
        }

        // Ponto de integração: 1) recolher itens por fonte via Apify/APIs;
        // 2) enviar para o modelo de síntese; 3) devolver no formato do contrato.
        $itens = Http::withToken($token)->throw()->timeout(30)
            ->post(config('services.apify.base_url', 'https://api.apify.com'), ['fontes' => $fontes])
            ->json();

        return $itens;
    }
}
