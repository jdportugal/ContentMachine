<?php

namespace App\Services\RealEstate;

use App\Services\Alertas\PaginaDoConsultor;
use Illuminate\Support\Facades\Http;

/**
 * iad Portugal: the "Encontre um consultor → Por nome" search (iadportugal.pt/api/agents) and the agent's page
 * (/consultor-imobiliario/<username>). iad matches the whole name typed, so a name with no exact match is asked
 * again by its last word and the closest ones are shown.
 */
class IadConsultores extends ConsultoresPorNome
{
    private const API = 'https://www.iadportugal.pt/api/agents';

    public function __construct(private PaginaDoConsultor $paginas) {}

    protected function buscar(string $nome): array
    {
        $agentes = $this->pedir($nome);
        if ($agentes === [] && str_contains($nome, ' ')) {
            $agentes = $this->pedir((string) last(explode(' ', $nome)));
        }

        return $agentes;
    }

    private function pedir(string $termo): array
    {
        $res = Http::timeout(10)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])
            ->get(self::API, ['searchTerm' => $termo, 'locale' => 'pt'])->throw()->json('items') ?? [];

        return array_values(array_map(fn ($r) => [
            'id' => (string) $r['userName'],
            'nome' => (string) ($r['fullName'] ?? ''),
            'agencia' => trim('iad Portugal'.(! empty($r['location']['place']) ? ' · '.$r['location']['place'] : '')),
        ], array_filter((array) $res, fn ($r) => $this->idValido((string) ($r['userName'] ?? '')))));
    }

    protected function lerImoveis(string $id): array
    {
        return $this->paginas->anuncios('iad', 'https://www.iadportugal.pt/consultor-imobiliario/'.$id);
    }

    protected function idValido(string $id): bool
    {
        return (bool) preg_match('/^[a-z0-9][a-z0-9._-]{1,80}$/i', $id);
    }
}
