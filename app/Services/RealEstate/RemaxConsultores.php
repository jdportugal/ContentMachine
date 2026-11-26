<?php

namespace App\Services\RealEstate;

use App\Services\Alertas\PaginaDoConsultor;
use Illuminate\Support\Facades\Http;

/** RE/MAX: its public agent search (the one behind remax.pt/pt/agentes) and the agent page's latest 10 listings. */
class RemaxConsultores extends ConsultoresPorNome
{
    private const API = 'https://remax.pt/api/Reference/UserAutocompleteSearch';

    public function __construct(private PaginaDoConsultor $paginas) {}

    protected function buscar(string $nome): array
    {
        $res = Http::timeout(10)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])
            ->get(self::API, ['searchValue' => $nome, 'size' => 200, 'isSpecial' => 'false'])->throw()->json('results') ?? [];

        return array_map(fn ($r) => ['id' => (string) ($r['agentID'] ?? ''), 'nome' => (string) ($r['name'] ?? ''), 'agencia' => (string) ($r['officeName'] ?? '')],
            array_filter((array) $res, fn ($r) => preg_match('/^\d{5,}$/', (string) ($r['agentID'] ?? ''))));
    }

    protected function lerImoveis(string $id): array
    {
        return $this->paginas->anuncios('remax', 'https://remax.pt/pt/agente/consultor/'.$id);
    }

    protected function idValido(string $id): bool
    {
        return (bool) preg_match('/^\d{5,}$/', $id);
    }
}
