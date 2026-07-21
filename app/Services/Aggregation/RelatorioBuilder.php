<?php

namespace App\Services\Aggregation;

use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Constrói um relatório de notícias a partir dos itens já agregados no vault,
 * para um período (um dia ou uma semana). Reutiliza o TopicsBuilder para os
 * tópicos e sintetiza resumo, destaques, fontes e ideias de guião.
 */
class RelatorioBuilder
{
    public function __construct(
        private readonly VaultContract $vault,
        private readonly TopicsBuilder $topicos,
    ) {}

    /**
     * @return array<string,mixed>
     */
    public function gerar(Carbon $inicio, Carbon $fim, string $modo): array
    {
        $itens = $this->itensDoPeriodo($inicio, $fim);
        $resultadoTopicos = $this->topicos->build($itens);
        $porPlataforma = $this->contarPorPlataforma($itens);

        $titulo = $modo === 'semana'
            ? 'Relatório — semana de '.$inicio->translatedFormat('d \d\e M').' a '.$fim->translatedFormat('d \d\e M \d\e Y')
            : 'Relatório — '.$inicio->translatedFormat('d \d\e F \d\e Y');

        return [
            'titulo' => $titulo,
            'modo' => $modo,
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'gerado_em' => now()->toIso8601String(),
            'total' => count($itens),
            'por_plataforma' => $porPlataforma,
            'metodo' => $resultadoTopicos['metodo'],
            'resumo' => $this->resumo(count($itens), $porPlataforma, $resultadoTopicos['topicos']),
            'topicos' => $resultadoTopicos['topicos'],
            'destaques' => $this->destaques($itens),
            'ideias_guiao' => $this->ideiasGuiao($resultadoTopicos['topicos']),
            'fontes' => $this->fontesUnicas($itens),
        ];
    }

    /** Corpo Markdown do relatório (legível no Obsidian). */
    public function corpoMarkdown(array $rel): string
    {
        $l = ["# {$rel['titulo']}", '', "> {$rel['total']} item(s) · método: {$rel['metodo']} · {$rel['gerado_em']}", '', '## Resumo', '', $rel['resumo'], ''];

        $l[] = '## Destaques';
        $l[] = '';
        foreach ($rel['destaques'] as $d) {
            $l[] = "- **[{$d['plataforma']}]** [{$d['titulo']}]({$d['url']}) — _{$d['angulo']}_ (relevância {$d['relevancia']})";
        }
        $l[] = '';

        $l[] = '## Tópicos';
        $l[] = '';
        foreach ($rel['topicos'] as $t) {
            $l[] = "### {$t['topico']}";
            foreach ($t['itens'] as $it) {
                $l[] = "- **[{$it['plataforma']}]** [{$it['titulo']}]({$it['url']})";
            }
            $l[] = '';
        }

        if ($rel['ideias_guiao'] !== []) {
            $l[] = '## Ideias de guião';
            $l[] = '';
            foreach ($rel['ideias_guiao'] as $ideia) {
                $l[] = "- {$ideia}";
            }
            $l[] = '';
        }

        if ($rel['fontes'] !== []) {
            $l[] = '## Fontes';
            $l[] = '';
            foreach ($rel['fontes'] as $f) {
                $l[] = "- <{$f}>";
            }
        }

        return implode("\n", $l);
    }

    /** @return array<int,AggregatedItem> */
    private function itensDoPeriodo(Carbon $inicio, Carbon $fim): array
    {
        $de = $inicio->toDateString();
        $ate = $fim->toDateString();

        return $this->vault->all('noticias')
            ->filter(fn (VaultNote $n) => $n->get('tipo') === 'item_agregado')
            ->filter(function (VaultNote $n) use ($de, $ate) {
                $d = (string) $n->get('data');

                return $d !== '' && $d >= $de && $d <= $ate;
            })
            ->map(fn (VaultNote $n) => new AggregatedItem(
                id: $n->slug(),
                plataforma: (string) $n->get('plataforma', ''),
                titulo: $n->title(),
                canal: (string) $n->get('canal', ''),
                data: (string) $n->get('data', ''),
                url: (string) $n->get('url', ''),
                thumbnail: (string) $n->get('thumbnail', ''),
                transcricao: $this->transcricaoDoCorpo($n->body),
                tags: array_values((array) $n->get('tags', [])),
                fontes: array_values((array) $n->get('fontes', [])),
            ))
            ->values()
            ->all();
    }

    /** Extrai o texto da transcrição do corpo Markdown da nota do item. */
    private function transcricaoDoCorpo(string $corpo): string
    {
        if (preg_match('/##\s*Transcri[cç][aã]o\s*\n+(.*)$/isu', $corpo, $m)) {
            $texto = trim($m[1]);

            return $texto === '_Sem transcrição disponível._' ? '' : $texto;
        }

        return '';
    }

    /**
     * @param  array<int,AggregatedItem>  $itens
     * @return array<string,int>
     */
    private function contarPorPlataforma(array $itens): array
    {
        $c = [];
        foreach ($itens as $item) {
            $c[$item->plataforma] = ($c[$item->plataforma] ?? 0) + 1;
        }
        arsort($c);

        return $c;
    }

    /**
     * Destaques por relevância (heurística: nº de fontes citadas + riqueza de tags).
     *
     * @param  array<int,AggregatedItem>  $itens
     * @return array<int,array<string,mixed>>
     */
    private function destaques(array $itens): array
    {
        $comScore = array_map(function (AggregatedItem $item) {
            $score = 55 + count($item->fontes) * 8 + min(count($item->tags), 8) * 3;
            $angulo = $item->fontes !== []
                ? count($item->fontes).' fonte(s) citada(s)'
                : ($item->tags[0] ?? $item->canal ?: $item->plataforma);

            return [
                'titulo' => $item->titulo,
                'url' => $item->url,
                'plataforma' => $item->plataforma,
                'canal' => $item->canal,
                'angulo' => $angulo,
                'relevancia' => min(99, $score),
            ];
        }, $itens);

        usort($comScore, fn ($a, $b) => $b['relevancia'] <=> $a['relevancia']);

        return array_slice($comScore, 0, 6);
    }

    /**
     * @param  array<int,array<string,mixed>>  $topicos
     * @return array<int,string>
     */
    private function ideiasGuiao(array $topicos): array
    {
        $ideias = [];
        foreach (array_slice($topicos, 0, 4) as $t) {
            $n = count($t['itens']);
            $ideias[] = "Peça sobre «{$t['topico']}» — {$n} referência(s) reunida(s) esta altura.";
        }

        return $ideias;
    }

    /**
     * @param  array<int,AggregatedItem>  $itens
     * @return array<int,string>
     */
    private function fontesUnicas(array $itens): array
    {
        $fontes = [];
        foreach ($itens as $item) {
            $fontes = array_merge($fontes, $item->fontes);
        }

        return array_values(array_slice(array_unique($fontes), 0, 20));
    }

    /**
     * @param  array<string,int>  $porPlataforma
     * @param  array<int,array<string,mixed>>  $topicos
     */
    private function resumo(int $total, array $porPlataforma, array $topicos): string
    {
        if ($total === 0) {
            return 'Sem itens agregados neste período. Corra «Agregar agora» para recolher conteúdo dos canais.';
        }

        $plataformas = implode(', ', array_keys($porPlataforma));
        $temas = collect($topicos)->take(3)->pluck('topico')->implode(', ');

        $frase = "{$total} item(s) reunido(s) de {$plataformas}.";
        if ($temas !== '') {
            $frase .= ' Principais temas: '.Str::lower($temas).'.';
        }

        return $frase;
    }
}
