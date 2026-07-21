<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;
use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Facades\Http;

/**
 * Driver real (ponto de integração). Padrão head-of-content:
 *   - YouTube  → TubeLab Outliers API
 *   - Instagram/TikTok/LinkedIn → actores Apify
 *
 * Requer credenciais em config/services. Sem elas, lança
 * DriverNotConfiguredException — a app usa 'fake' por defeito, por isso
 * este caminho só é atingido após configuração explícita.
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
     * Chama a API externa. Isolado para facilitar testes/mock quando as
     * credenciais existirem. Enquanto não houver chave, falha de forma clara.
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

        // Ponto de integração: mapear a resposta da API para o formato normalizado
        // (id, plataforma, tipo, titulo, url, publicado_em, views, likes, comentarios,
        //  partilhas, guardados). Http já disponível para quando as chaves existirem.
        // Ex.: return Http::withToken($token)->get(...)->json('itens', []);
        unset($recurso); // recurso usado no mapeamento real por endpoint

        return Http::withToken($token)->throw()->timeout(20)
            ->get(config('services.apify.base_url', 'https://api.apify.com'))
            ->json('itens', []);
    }
}
