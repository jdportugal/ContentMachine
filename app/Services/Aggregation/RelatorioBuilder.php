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
        private readonly LlmClient $llm,
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

        [$redacao, $redacaoMetodo] = $this->redacao($itens, $resultadoTopicos['topicos'], $modo, $inicio, $fim);

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
            'redacao' => $redacao,
            'redacao_metodo' => $redacaoMetodo,
            'topicos' => $resultadoTopicos['topicos'],
            'destaques' => $this->destaques($itens),
            'ideias_guiao' => $this->ideiasGuiao($resultadoTopicos['topicos']),
            'fontes' => $this->fontesUnicas($itens),
        ];
    }

    /** Corpo Markdown do relatório (legível no Obsidian). */
    public function corpoMarkdown(array $rel): string
    {
        $l = ["# {$rel['titulo']}", '', "> {$rel['total']} item(s) · método: {$rel['metodo']} · {$rel['gerado_em']}", '', '## Síntese', '', $rel['redacao'] ?? '', '', '## Resumo', '', $rel['resumo'], ''];

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
                descricao: $this->descricaoDaNota($n),
                transcricao: $this->transcricaoDoCorpo($n->body),
                tags: array_values((array) $n->get('tags', [])),
                fontes: array_values((array) $n->get('fontes', [])),
            ))
            ->values()
            ->all();
    }

    /** Sinopse de conteúdo: o resumo por IA (preferido) ou o início da transcrição. */
    private function descricaoDaNota(VaultNote $n): string
    {
        $resumo = trim((string) $n->get('resumo', ''));

        return $resumo !== '' ? $resumo : Str::limit($this->transcricaoDoCorpo($n->body), 240, '');
    }

    /** Extrai (e limpa) o texto da transcrição do corpo Markdown da nota do item. */
    private function transcricaoDoCorpo(string $corpo): string
    {
        if (! preg_match('/##\s*Transcri[cç][aã]o\s*\n+(.*)$/isu', $corpo, $m)) {
            return '';
        }

        $texto = trim($m[1]);
        if ($texto === '_Sem transcrição disponível._') {
            return '';
        }

        // Remove marcadores de legenda ([música], [music], (risos)…) que poluem
        // os tópicos e a redação.
        $texto = preg_replace('/[\[\(][^\]\)]{0,30}[\]\)]/u', ' ', $texto) ?? $texto;

        return trim(preg_replace('/[ \t]+/', ' ', $texto) ?? $texto);
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
     * Redação — texto escrito sobre tudo o que os canais estão a cobrir.
     * Usa o LLM quando há chave; senão compõe uma síntese a partir dos tópicos
     * e de frases reais das transcrições.
     *
     * @param  array<int,AggregatedItem>  $itens
     * @param  array<int,array<string,mixed>>  $topicos
     */
    private function redacao(array $itens, array $topicos, string $modo, Carbon $inicio, Carbon $fim): array
    {
        if ($itens === []) {
            return ['Não há conteúdo agregado neste período para redigir. Corra a recolha e tente de novo.', 'vazio'];
        }

        if ($this->llm->disponivel()) {
            $texto = $this->redacaoViaLlm($itens, $modo, $inicio, $fim);
            if ($texto !== null && $texto !== '') {
                return [$texto, $this->llm->fornecedorAtivo() ?? 'llm'];
            }
        }

        return [$this->redacaoHeuristica($itens, $topicos, $modo, $inicio, $fim), 'heuristica'];
    }

    /** @param array<int,AggregatedItem> $itens */
    private function redacaoViaLlm(array $itens, string $modo, Carbon $inicio, Carbon $fim): ?string
    {
        $material = collect($itens)->take(20)->map(fn (AggregatedItem $i) => [
            'titulo' => $i->titulo,
            'canal' => $i->canal,
            'plataforma' => $i->plataforma,
            'resumo' => Str::limit(trim($i->descricao), 500, ''),
            'transcricao' => Str::limit(trim($i->transcricao), 3500, ''),
        ])->all();

        $periodo = $modo === 'semana'
            ? 'a semana de '.$inicio->translatedFormat('d/m').' a '.$fim->translatedFormat('d/m')
            : 'o dia '.$inicio->translatedFormat('d/m/Y');

        $prompt = 'És um editor de notícias português. Escreve, em PORTUGUÊS EUROPEU (de Portugal — evita brasileirismos '
            .'como «você» ou gerúndios «está fazendo»; usa «está a fazer», «ecrã», «utilizador»), um relatório APROFUNDADO '
            ."sobre o que os canais acompanhados cobriram em {$periodo}.\n\n"
            ."Estrutura obrigatória:\n"
            ."1) Um parágrafo de abertura com o fio condutor do período.\n"
            .'2) UM PARÁGRAFO POR VÍDEO (usa TODOS os itens do material): identifica o criador/canal e o título, '
            .'a tese central, e 2 a 3 pontos CONCRETOS do que é efectivamente dito — apoia-te na transcrição e na '
            ."descrição, não apenas no título. Menciona nomes próprios, produtos e números que apareçam (ex.: «Hermes agent»).\n"
            ."3) Um parágrafo final a ligar os temas transversais entre os vídeos.\n\n"
            .'Vai fundo no conteúdo — NÃO te limites a uma frase por vídeo nem a citar uma única linha solta. '
            .'Não inventes factos; usa apenas o material. Texto corrido, sem títulos nem listas.'
            ."\n\n".json_encode($material, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $this->llm->texto($prompt);
    }

    /**
     * @param  array<int,AggregatedItem>  $itens
     * @param  array<int,array<string,mixed>>  $topicos
     */
    private function redacaoHeuristica(array $itens, array $topicos, string $modo, Carbon $inicio, Carbon $fim): string
    {
        $porTitulo = [];
        foreach ($itens as $item) {
            $porTitulo[$item->titulo] = $item;
        }

        $periodo = $modo === 'semana'
            ? 'na semana de '.$inicio->translatedFormat('d \d\e M').' a '.$fim->translatedFormat('d \d\e M')
            : 'no dia '.$inicio->translatedFormat('d \d\e F \d\e Y');

        $plataformas = implode(', ', array_keys($this->contarPorPlataforma($itens)));
        $temas = collect($topicos)->take(4)->pluck('topico');

        $paras = [];
        $paras[] = 'Os canais acompanhados publicaram '.count($itens).' peça(s) '.$periodo.', em '.$plataformas.'.'
            .($temas->isNotEmpty() ? ' A cobertura concentrou-se em '.Str::lower($temas->implode(', ')).'.' : '');

        foreach (array_slice($topicos, 0, 4) as $t) {
            $n = count($t['itens']);
            $canais = collect($t['itens'])->pluck('plataforma')->unique()->implode(', ');

            $frase = '';
            foreach ($t['itens'] as $it) {
                $item = $porTitulo[$it['titulo']] ?? null;
                if ($item && trim($item->transcricao) !== '') {
                    $frase = $this->primeiraFrase($item->transcricao);
                    break;
                }
            }

            $p = 'Em torno de «'.$t['topico'].'» reuniram-se '.$n.' peça(s) ('.$canais.').';
            if ($frase !== '') {
                $p .= ' A dada altura ouve-se: «'.$frase.'».';
            }
            $paras[] = $p;
        }

        return implode("\n\n", $paras);
    }

    /** Primeira frase legível de um texto, até $max caracteres. */
    private function primeiraFrase(string $texto, int $max = 220): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
        if ($texto === '') {
            return '';
        }

        if (preg_match('/^(.{20,}?[.!?])\s/u', $texto.' ', $m)) {
            return Str::limit($m[1], $max, '…');
        }

        return Str::limit($texto, $max, '…');
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
