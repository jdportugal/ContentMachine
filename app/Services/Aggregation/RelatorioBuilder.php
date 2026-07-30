<?php

namespace App\Services\Aggregation;

use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds a news report from the items already aggregated in the vault,
 * for a period (a day or a week). Reuses the TopicsBuilder for the
 * topics and synthesizes summary, highlights, sources and script ideas.
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
    /** @param  string  $idioma  Output language for the write-up (e.g. 'English', 'European Portuguese'). */
    public function gerar(Carbon $inicio, Carbon $fim, string $modo, string $idioma = 'English'): array
    {
        $itens = $this->itensDoPeriodo($inicio, $fim);
        $resultadoTopicos = $this->topicos->build($itens);
        $porPlataforma = $this->contarPorPlataforma($itens);

        $rotulo = str_contains(strtolower($idioma), 'portug') ? 'Relatório' : 'Report';
        $titulo = $modo === 'semana'
            ? $rotulo.' — '.$inicio->translatedFormat('d M').' – '.$fim->translatedFormat('d M Y')
            : $rotulo.' — '.$inicio->translatedFormat('d M Y');

        [$redacao, $redacaoMetodo] = $this->redacao($itens, $resultadoTopicos['topicos'], $modo, $inicio, $fim, $idioma);

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

    /** Markdown body of the report (readable in Obsidian). */
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

    /** Content synopsis: the AI summary (preferred) or the start of the transcript. */
    private function descricaoDaNota(VaultNote $n): string
    {
        $resumo = trim((string) $n->get('resumo', ''));

        return $resumo !== '' ? $resumo : Str::limit($this->transcricaoDoCorpo($n->body), 240, '');
    }

    /** Extracts (and cleans) the transcript text from the item note's Markdown body. */
    private function transcricaoDoCorpo(string $corpo): string
    {
        if (! preg_match('/##\s*Transcri[cç][aã]o\s*\n+(.*)$/isu', $corpo, $m)) {
            return '';
        }

        $texto = trim($m[1]);
        if ($texto === '_Sem transcrição disponível._') {
            return '';
        }

        // Remove caption markers ([música], [music], (risos)…) that pollute
        // the topics and the script.
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
     * Highlights by relevance (heuristic: number of cited sources + tag richness).
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
     * Script — written text about everything the channels are covering.
     * Uses the LLM when there is a key; otherwise composes a synthesis from the topics
     * and real sentences from the transcripts.
     *
     * @param  array<int,AggregatedItem>  $itens
     * @param  array<int,array<string,mixed>>  $topicos
     */
    private function redacao(array $itens, array $topicos, string $modo, Carbon $inicio, Carbon $fim, string $idioma): array
    {
        if ($itens === []) {
            return ['No content aggregated in this period. Run the collection and try again.', 'vazio'];
        }

        if ($this->llm->disponivel()) {
            $texto = $this->redacaoViaLlm($itens, $modo, $inicio, $fim, $idioma);
            if ($texto !== null && $texto !== '') {
                return [$texto, $this->llm->fornecedorAtivo() ?? 'llm'];
            }
        }

        return [$this->redacaoHeuristica($itens, $topicos, $modo, $inicio, $fim), 'heuristica'];
    }

    /** @param array<int,AggregatedItem> $itens */
    private function redacaoViaLlm(array $itens, string $modo, Carbon $inicio, Carbon $fim, string $idioma): ?string
    {
        $material = collect($itens)->take(20)->map(fn (AggregatedItem $i) => [
            'subject' => $i->titulo, // topic hint — must NOT be mentioned in the bit
            'transcript' => Str::limit(trim($i->transcricao), 3500, ''),
            'sources' => array_values(array_slice($i->fontes, 0, 6)),
        ])->all();

        $periodo = $modo === 'semana'
            ? 'the last 7 days (until '.$fim->translatedFormat('d/m/Y').')'
            : 'the day '.$inicio->translatedFormat('d/m/Y');

        $prompt = 'You are an AI-news editor. '
            ."From the material below — transcripts of creators' videos and the sources they cite, for {$periodo} — "
            ."produce a set of SHORT, SELF-CONTAINED news bits.\n\n"
            ."OUTPUT LANGUAGE: write EVERYTHING in {$idioma}.\n\n"
            ."STRUCTURE — this is the most important rule:\n"
            .'- Output a SERIES OF SEPARATE BITS, one per relevant news item. Do NOT write a continuous, '
            ."connected narration or a single flowing script.\n"
            .'- Each bit MUST stand entirely on its own: NO overall intro, NO outro, NO hook, NO references to '
            ."other bits or to «this week»/«today» — a reader could see any single bit in isolation.\n"
            .'- Format each bit as: a bold one-line headline (`**Headline**`), then 2–4 sentences. '
            ."Separate consecutive bits with a line containing only `---`.\n\n"
            ."TONE:\n"
            .'- Clear and engaging. Do NOT just state facts: EXPLAIN what happened and why it matters '
            ."(«…and this matters because…»).\n\n"
            ."CONTENT:\n"
            .'- Cover ONLY relevant news: releases, new models/products, major updates, acquisitions, '
            .'funding rounds, studies, numbers. IGNORE tutorials, personal opinions, promotions, sponsorships and '
            ."«subscribe» calls to action. Not all items have to be used.\n"
            .'- IMPERSONAL about the origin: NEVER mention the videos, the creators or the channels («in a video», «the creator '
            ."says», «this channel»). Present the news directly.\n"
            ."- Mention proper names, products, dates and concrete numbers (e.g. «Fable 5», «Kimi K3», «Hermes»).\n"
            .'- If a news item lacks CONTEXT, USE web search and the given sources to confirm and enrich it. '
            ."Do NOT invent: if you cannot confirm, be cautious or omit it.\n\n"
            ."MATERIAL (transcripts + sources):\n"
            .json_encode($material, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return $this->llm->texto($prompt, comFerramentas: true);
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

    /** First readable sentence of a text, up to $max characters. */
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
