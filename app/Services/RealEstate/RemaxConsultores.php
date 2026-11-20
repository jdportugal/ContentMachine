<?php

namespace App\Services\RealEstate;

use App\Services\Alertas\PaginaDoConsultor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * "Procurar pelo meu nome" for RE/MAX agents: the agent types their name, we ask RE/MAX's own
 * public agent search (the one behind remax.pt/pt/agentes) and show who matches; they pick
 * themselves and we read their public agent page for their newest listings. Nothing is copied
 * in advance: only what a visitor searches for is read, live, and kept a short while in cache.
 */
class RemaxConsultores
{
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    private const API = 'https://remax.pt/api/Reference/UserAutocompleteSearch';

    public const MAX = 8;

    public function __construct(private PaginaDoConsultor $paginas) {}

    /**
     * Agents whose name holds every word typed (accents and case ignored), best matches first.
     *
     * @return list<array{id: string, nome: string, agencia: string}>
     */
    public function procurar(string $nome): array
    {
        $nome = trim((string) preg_replace('/\s+/u', ' ', $nome));
        if (mb_strlen($nome) < 3) {
            return [];
        }

        return Cache::remember('remax-procura:'.md5(self::normal($nome)), now()->addHours(6), function () use ($nome) {
            $res = Http::timeout(10)->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json'])
                ->get(self::API, ['searchValue' => $nome, 'size' => 200, 'isSpecial' => 'false'])->throw()->json('results') ?? [];

            return self::ordenar((array) $res, $nome);
        });
    }

    /** @return list<array{id: string, nome: string, agencia: string}> */
    public static function ordenar(array $resultados, string $nome): array
    {
        $palavras = array_values(array_filter(explode(' ', self::normal($nome))));
        $out = [];
        foreach ($resultados as $r) {
            $id = (string) ($r['agentID'] ?? '');
            $n = trim((string) ($r['name'] ?? ''));
            if (! preg_match('/^\d{5,}$/', $id) || $n === '') {
                continue;
            }
            $dele = explode(' ', self::normal($n));
            $acertos = count(array_filter($palavras, fn ($p) => (bool) array_filter($dele, fn ($d) => str_starts_with($d, $p))));
            if ($acertos === 0) {
                continue;
            }
            // Name and office only: the agent's photo is not shown.
            $out[] = ['id' => $id, 'nome' => $n, 'agencia' => trim((string) ($r['officeName'] ?? '')), 'acertos' => $acertos];
        }
        // Everyone who matches every word typed; if nobody does, the closest ones.
        $todos = array_values(array_filter($out, fn ($r) => $r['acertos'] === count($palavras)));
        $lista = $todos !== [] ? $todos : $out;
        usort($lista, fn ($a, $b) => [$b['acertos'], $a['nome']] <=> [$a['acertos'], $b['nome']]);

        return array_map(fn ($r) => array_diff_key($r, ['acertos' => 1]), array_slice($lista, 0, self::MAX));
    }

    /**
     * The agent's newest listings (RE/MAX shows the latest 10 on the agent page), each with its link, photo and price.
     *
     * @return list<array{id: string, titulo: string, url: ?string, foto: ?string, preco: ?int}>
     */
    public function imoveis(string $id): array
    {
        if (! preg_match('/^\d{5,}$/', $id)) {
            return [];
        }

        return Cache::remember('remax-imoveis:'.$id, now()->addHour(), fn () => array_values(array_filter(
            $this->paginas->anuncios('remax', 'https://remax.pt/pt/agente/consultor/'.$id),
            fn ($a) => ! empty($a['url'])
        )));
    }

    private static function normal(string $s): string
    {
        return mb_strtolower(trim(Str::ascii($s)));
    }
}
