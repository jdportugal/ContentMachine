<?php

namespace App\Services\RealEstate;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * "Procurar pelo meu nome": the agent types their name, we ask their network's own public agent search and show
 * who matches; they pick themselves and we read their newest listings from the network. Nothing is copied in
 * advance: only what a visitor searches for is read, live, and kept a short while in cache.
 * One subclass per network (PORTAIS): how to search it and how to read an agent's listings.
 */
abstract class ConsultoresPorNome
{
    public const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    public const MAX = 8;

    /** The networks agents can search themselves in, by the landing's agency key. */
    public const PORTAIS = [
        'remax' => RemaxConsultores::class,
        'iad' => IadConsultores::class,
        'century21' => Century21Consultores::class,
    ];

    public static function para(string $agencia): ?self
    {
        return isset(self::PORTAIS[$agencia]) ? app(self::PORTAIS[$agencia]) : null;
    }

    /** The network's agents for a name: [id, nome, agencia] each (id is what imoveis() takes). */
    abstract protected function buscar(string $nome): array;

    /** The agent's newest listings: [id, titulo, url, foto?, preco?] each. */
    abstract protected function lerImoveis(string $id): array;

    /** Ids this network gives its agents (only those reach imoveis()). */
    abstract protected function idValido(string $id): bool;

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

        return Cache::remember(static::class.':procura:'.md5(self::normal($nome)), now()->addHours(6),
            fn () => self::ordenar($this->buscar($nome), $nome));
    }

    /**
     * The agent's newest listings, each with its link (and photo and price when the network gives them).
     *
     * @return list<array{id: string, titulo: string, url: ?string, foto: ?string, preco: ?int}>
     */
    public function imoveis(string $id): array
    {
        if (! $this->idValido($id)) {
            return [];
        }

        return Cache::remember(static::class.':imoveis:'.$id, now()->addHour(), fn () => array_values(array_map(
            fn ($a) => ['id' => (string) $a['id'], 'titulo' => (string) ($a['titulo'] ?? ''), 'url' => $a['url'] ?? null, 'foto' => $a['foto'] ?? null, 'preco' => $a['preco'] ?? null],
            array_filter($this->lerImoveis($id), fn ($a) => ! empty($a['url']) && ($a['id'] ?? '') !== '')
        )));
    }

    /**
     * Everyone who matches every word typed; if nobody does, the closest ones. Name and agency only.
     *
     * @param  list<array{id: string, nome: string, agencia: string}>  $agentes
     * @return list<array{id: string, nome: string, agencia: string}>
     */
    public static function ordenar(array $agentes, string $nome): array
    {
        $palavras = array_values(array_filter(explode(' ', self::normal($nome))));
        $out = [];
        $vistos = [];
        foreach ($agentes as $a) {
            $id = (string) ($a['id'] ?? '');
            $n = trim((string) ($a['nome'] ?? ''));
            if ($id === '' || $n === '' || isset($vistos[$id])) {
                continue;
            }
            $vistos[$id] = true;
            $dele = explode(' ', self::normal($n));
            $acertos = count(array_filter($palavras, fn ($p) => (bool) array_filter($dele, fn ($d) => str_starts_with($d, $p))));
            if ($acertos > 0) {
                $out[] = ['id' => $id, 'nome' => $n, 'agencia' => trim((string) ($a['agencia'] ?? '')), 'acertos' => $acertos];
            }
        }
        $todos = array_values(array_filter($out, fn ($r) => $r['acertos'] === count($palavras)));
        $lista = $todos !== [] ? $todos : $out;
        usort($lista, fn ($a, $b) => [$b['acertos'], $a['nome']] <=> [$a['acertos'], $b['nome']]);

        return array_map(fn ($r) => array_diff_key($r, ['acertos' => 1]), array_slice($lista, 0, self::MAX));
    }

    protected static function normal(string $s): string
    {
        return mb_strtolower(trim(Str::ascii($s)));
    }
}
