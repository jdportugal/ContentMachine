<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Http;

/** Century 21: the "Consultores → Pesquise por nome" search (century21.pt/api/agents) and the agent's listings (api/properties). */
class Century21Consultores extends ConsultoresPorNome
{
    private const API = 'https://century21.pt/api/';

    protected function buscar(string $nome): array
    {
        $res = $this->http()->get(self::API.'agents', ['page' => 1, 'query' => $nome])->throw()->json('data') ?? [];

        return array_values(array_map(fn ($r) => [
            'id' => (string) $r['handler'],
            'nome' => (string) (($r['display_name'] ?? '') ?: ($r['name'] ?? '')),
            'agencia' => (string) ($r['agency']['name'] ?? ''),
        ], array_filter((array) $res, fn ($r) => $this->idValido((string) ($r['handler'] ?? '')))));
    }

    protected function lerImoveis(string $id): array
    {
        $res = $this->http()->get(self::API.'properties', ['limit' => 10, 'page' => 1, 'agent_handler' => $id])->throw()->json('data') ?? [];

        return array_map(function ($p) {
            $titulo = $p['title'] ?? '';

            return [
                'id' => (string) ($p['reference'] ?? $p['id'] ?? ''),
                'titulo' => trim(is_array($titulo) ? (string) ($titulo['pt'] ?? reset($titulo) ?: '') : (string) $titulo),
                'url' => $p['link'] ?? null,
                'foto' => $p['images'][0] ?? null,
                'preco' => empty($p['price_hidden']) && is_numeric($p['price'] ?? null) && $p['price'] > 0 ? (int) $p['price'] : null,
            ];
        }, (array) $res);
    }

    protected function idValido(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/i', $id);
    }

    private function http()
    {
        return Http::timeout(10)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json']);
    }
}
