<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;
use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Real driver (integration point). Head-of-content pattern:
 *   - YouTube  → TubeLab Outliers API
 *   - Instagram/TikTok/LinkedIn → Apify actors
 *
 * Requires credentials in config/services. Without them, it throws
 * DriverNotConfiguredException — the app uses 'fake' by default, so
 * this path is only reached after explicit configuration.
 */
class ApiMonitoringDriver implements MonitoringDriver
{
    public function __construct(
        private readonly string $plataforma,
        private readonly EngagementScorer $scorer,
    ) {}

    public function plataforma(): string
    {
        return $this->plataforma;
    }

    public function resumo(): array
    {
        return $this->pontuar($this->obterRaw('resumo'));
    }

    public function conteudosRecentes(int $limite = 12): array
    {
        return array_slice($this->pontuar($this->obterRaw('recentes')), 0, $limite);
    }

    public function ultimoPorTipo(): array
    {
        $porTipo = [];
        foreach ($this->conteudosRecentes(100) as $item) {
            $porTipo[$item['tipo']] ??= $item;
        }

        return array_values($porTipo);
    }

    public function melhores(int $limite = 5): array
    {
        $itens = $this->conteudosRecentes(100);
        usort($itens, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($itens, 0, $limite);
    }

    private function pontuar(array $itens): array
    {
        if ($itens === [] || ! isset($itens[0]['views'])) {
            return $itens;
        }

        $mediana = $this->scorer->mediana(array_column($itens, 'views'));

        return array_map(fn ($i) => array_merge($i, $this->scorer->score($this->plataforma, $i, $mediana)), $itens);
    }

    /**
     * Calls the external API. Isolated to ease testing/mocking once the
     * credentials exist. While there is no key, it fails clearly.
     */
    private function obterRaw(string $recurso): array
    {
        [$chave, $token] = match ($this->plataforma) {
            'youtube' => ['services.tubelab.token', config('services.tubelab.token')],
            default => ['services.apify.token', config('services.apify.token')],
        };

        if (blank($token)) {
            throw DriverNotConfiguredException::for($this->plataforma, $chave);
        }

        // Integration point: map the API response to the normalized format
        // (id, plataforma, tipo, titulo, url, publicado_em, views, likes, comentarios,
        //  partilhas, guardados). Http already available for when the keys exist.
        // e.g. return Http::withToken($token)->get(...)->json('itens', []);
        unset($recurso); // resource used in the real per-endpoint mapping

        return Http::withToken($token)->throw()->timeout(20)
            ->get(config('services.apify.base_url', 'https://api.apify.com'))
            ->json('itens', []);
    }
}
