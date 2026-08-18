<?php

namespace App\Services\Aggregation;

use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Builds a report from the items already aggregated in the vault, for a period
 * (a day or a week). Reuses the TopicsBuilder for the topics and synthesizes
 * summary, highlights, sources and script ideas.
 *
 * Two kinds, same pipeline — only the write-up differs:
 *   - 'noticias' → news bits: what happened and why it matters.
 *   - 'dicas'    → tool-tip scripts: the practical trick buried in the material
 *     («this new Claude Code skill», «burning tokens? try this»), written to be
 *     read out loud as a short-form video.
 */
class RelatorioBuilder
{
    public const TIPO_NOTICIAS = 'noticias';

    public const TIPO_DICAS = 'dicas';

    public function __construct(
        private readonly VaultContract $vault,
        private readonly TopicsBuilder $topicos,
        private readonly LlmClient $llm,
    ) {}

    /**
     * @param  string  $idioma  Output language for the write-up (e.g. 'English', 'European Portuguese').
     * @param  string  $tipo  self::TIPO_NOTICIAS | self::TIPO_DICAS
     * @return array<string,mixed>
     */
    public function gerar(Carbon $inicio, Carbon $fim, string $modo, string $idioma = 'English', string $tipo = self::TIPO_NOTICIAS): array
    {
        $itens = $this->itensDoPeriodo($inicio, $fim);
        $resultadoTopicos = $this->topicos->build($itens);
        $porPlataforma = $this->contarPorPlataforma($itens);

        $pt = str_contains(strtolower($idioma), 'portug');
        $rotulo = $tipo === self::TIPO_DICAS
            ? ($pt ? 'Dicas' : 'Tool tips')
            : ($pt ? 'Relatório' : 'Report');
        $titulo = $modo === 'semana'
            ? $rotulo.' — '.$inicio->translatedFormat('d M').' – '.$fim->translatedFormat('d M Y')
            : $rotulo.' — '.$inicio->translatedFormat('d M Y');

        [$redacao, $redacaoMetodo] = $this->redacao($itens, $resultadoTopicos['topicos'], $modo, $inicio, $fim, $idioma, $tipo);

        return [
            'titulo' => $titulo,
            'tipo' => $tipo,
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
            'ideias_guiao' => $this->ideiasGuiao($resultadoTopicos['topicos'], $tipo),
            'fontes' => $this->fontesUnicas($itens),
        ];
    }

    /** Markdown body of the report (readable in Obsidian). */
    public function corpoMarkdown(array $rel): string
    {
        $dicas = ($rel['tipo'] ?? self::TIPO_NOTICIAS) === self::TIPO_DICAS;
        $sintese = $dicas ? '## Guiões de dicas' : '## Síntese';

        $l = ["# {$rel['titulo']}", '', "> {$rel['total']} item(s) · método: {$rel['metodo']} · {$rel['gerado_em']}", '', $sintese, '', $rel['redacao'] ?? '', '', '## Resumo', '', $rel['resumo'], ''];

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
            $l[] = $dicas ? '## Ângulos por explorar' : '## Ideias de guião';
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
    private function ideiasGuiao(array $topicos, string $tipo = self::TIPO_NOTICIAS): array
    {
        $ideias = [];
        foreach (array_slice($topicos, 0, 4) as $t) {
            $n = count($t['itens']);
            $ideias[] = $tipo === self::TIPO_DICAS
                ? "Tip angle on «{$t['topico']}» — {$n} reference(s) to mine for a practical trick."
                : "Peça sobre «{$t['topico']}» — {$n} referência(s) reunida(s) esta altura.";
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
    private function redacao(array $itens, array $topicos, string $modo, Carbon $inicio, Carbon $fim, string $idioma, string $tipo = self::TIPO_NOTICIAS): array
    {
        if ($itens === []) {
            return ['No content aggregated in this period. Run the collection and try again.', 'vazio'];
        }

        // Tips are their own pipeline step, so they can be pinned to their own key.
        if ($tipo === self::TIPO_DICAS) {
            $this->llm->paraPasso('noticias_dicas');
        }

        if ($this->llm->disponivel()) {
            $texto = $tipo === self::TIPO_DICAS
                ? $this->dicasViaLlm($itens, $idioma)
                : $this->redacaoViaLlm($itens, $modo, $inicio, $fim, $idioma);
            if ($texto !== null && $texto !== '') {
                return [$texto, $this->llm->fornecedorAtivo() ?? 'llm'];
            }
        }

        // A tip has to BE a real trick someone demonstrated; there is no honest way
        // to compose one heuristically from transcripts without inventing it. So say
        // so plainly — the topics, highlights and sources below still stand.
        if ($tipo === self::TIPO_DICAS) {
            return ['Tool tips need an AI provider — set an LLM key in Settings (or pin one to the news-writing step) and generate again.', 'sem-llm'];
        }

        return [$this->redacaoHeuristica($itens, $topicos, $modo, $inicio, $fim), 'heuristica'];
    }

    /**
     * Tool-usage tips, written as short-form scripts. Same material as the news
     * write-up, but mining it for the PRACTICAL move — the setting, the flag, the
     * workflow — rather than for what was announced.
     *
     * @param  array<int,AggregatedItem>  $itens
     */
    private function dicasViaLlm(array $itens, string $idioma): ?string
    {
        $material = collect($itens)->take(20)->map(fn (AggregatedItem $i) => [
            'subject' => $i->titulo, // topic hint — must NOT be mentioned in the script
            'transcript' => Str::limit(trim($i->transcricao), 3500, ''),
            'sources' => array_values(array_slice($i->fontes, 0, 6)),
        ])->all();

        return $this->llm->texto(
            'You write SHORT-FORM VIDEO SCRIPTS about how to actually USE AI tools. '
            ."From the material below — transcripts of creators' videos and the sources they cite — "
            ."extract the practical TIPS and turn each into its own script.\n\n"
            ."OUTPUT LANGUAGE: write EVERYTHING in {$idioma}.\n\n"
            ."WHAT COUNTS AS A TIP — the whole point:\n"
            .'- A concrete, actionable move with a tool: a feature most people miss, a setting or flag, a workflow, '
            ."a prompt pattern, a way to cut cost or time, a fix for a common failure.\n"
            .'- Examples of the register: «this new Claude Code skill does X for you», '
            ."«burning tokens on the new model? do this instead».\n"
            .'- NOT a tip: an announcement, a funding round, a benchmark, a release date, an opinion. '
            ."If the material only announces something, SKIP it — do not stretch news into a fake tip.\n\n"
            ."STRUCTURE — one script per tip:\n"
            .'- Start with a bold one-line HOOK (`**Hook**`) written as the problem the viewer already has '
            ."(«If you're …, you're wasting …»), or as the thing they don't know exists.\n"
            .'- Then 3–6 short sentences, in this order: the problem → the tip → HOW to do it, concretely '
            ."(name the command, the setting, the menu, the exact wording).\n"
            .'- End with the one-line payoff: what changes for them.'
            ."\n- Separate consecutive scripts with a line containing only `---`.\n"
            .'- Each script MUST stand alone: NO overall intro or outro, NO «in this video», '
            ."NO references to the other scripts or to «this week».\n\n"
            ."RULES:\n"
            .'- Speak DIRECTLY to the viewer («you»), spoken register, short sentences — this gets read out loud.\n'
            .'- NEVER mention the videos, the creators or the channels you got this from. State the tip as your own.\n'
            .'- Name the tool, the model, the command and the numbers exactly («Claude Code», «--resume», «60% fewer tokens»). '
            ."A vague tip is worthless.\n"
            .'- Do NOT invent steps. If the material does not say HOW, use web search and the given sources to confirm the '
            ."exact procedure; if you still cannot confirm it, drop that tip.\n"
            .'- Quality over quantity: 3 real tips beat 10 padded ones. If the material holds no genuine tip, '
            ."say so in one line instead of inventing.\n\n"
            ."MATERIAL (transcripts + sources):\n"
            .json_encode($material, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
            comFerramentas: true,
        );
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
            ."- Format each bit EXACTLY as:\n"
            ."    **Headline**\n"
            ."    > Lead sentence\n"
            ."    Body (4–6 sentences)\n"
            ."  Separate consecutive bits with a line containing only `---`.\n"
            .'- The LEAD (the `>` line) is ONE punchy sentence that may sit ON SCREEN for the whole video: '
            .'it must carry the entire story by itself — who did what, with the number or name that makes it matter '
            .'(«OpenAI cuts GPT-5 API prices by 60%», not «Big news from OpenAI»). '
            ."Max ~12 words, no filler, no cliffhanger bait, present tense.\n"
            .'- The BODY does not repeat the lead — it goes DEEPER: the concrete details (versions, prices, dates, '
            .'availability), the context that explains it (what came before, who it competes with), why it matters, '
            ."and what happens next when known. Substance over padding: every sentence must add a fact or a consequence.\n\n"
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
