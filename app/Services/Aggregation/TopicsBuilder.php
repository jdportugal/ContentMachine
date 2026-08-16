<?php

namespace App\Services\Aggregation;

use App\Services\Projects\ProjectLanguage;
use Illuminate\Support\Str;

/**
 * Derives a list of topics ("what has already been covered and is live") from
 * a set of items from one day. Uses the app's LLM chain (Claude → GPT → DeepSeek),
 * so the configured provider is the one that names the topics; it only falls back
 * to a deterministic tag/keyword grouping when NO provider is configured. That
 * fallback produces topic names like "Said", so it is a last resort, never a
 * default. Never throws.
 */
class TopicsBuilder
{
    public function __construct(private readonly LlmClient $llm) {}

    /** Stop words (PT/EN) ignored in keyword extraction. */
    private const STOPWORDS = [
        'the', 'and', 'for', 'you', 'your', 'with', 'this', 'that', 'from', 'have', 'has', 'are', 'was', 'were', 'will',
        'what', 'how', 'why', 'who', 'they', 'their', 'them', 'about', 'just', 'like', 'into', 'more', 'some', 'then',
        'than', 'been', 'only', 'over', 'also', 'make', 'made', 'when', 'which', 'there', 'here', 'very', 'much', 'well',
        'even', 'still', 'because', 'would', 'could', 'should', 'these', 'those', 'them', 'want', 'need', 'know', 'going',
        'como', 'para', 'uma', 'que', 'dos', 'das', 'com', 'por', 'mais', 'muito', 'sobre', 'isto', 'isso', 'você',
        'então', 'porque', 'quando', 'também', 'pode', 'podem', 'vamos', 'fazer', 'sua', 'seu', 'meu', 'minha', 'não',
        'ser', 'está', 'estão', 'tem', 'têm', 'aqui', 'entre', 'depois', 'antes', 'cada', 'todo', 'toda', 'todos', 'todas',
    ];

    /**
     * @param  array<int,AggregatedItem>  $itens
     * @return array{gerado_em:string,metodo:string,topicos:array<int,array{topico:string,itens:array<int,array{titulo:string,url:string,plataforma:string}>,fontes:array<int,string>}>}
     */
    public function build(array $itens): array
    {
        $topicos = $this->viaLlm($itens) ?? $this->viaHeuristica($itens);

        return [
            'gerado_em' => now()->toIso8601String(),
            'metodo' => $topicos['metodo'],
            'topicos' => $topicos['topicos'],
        ];
    }

    /**
     * @param  array<int,AggregatedItem>  $itens
     * @return array{metodo:string,topicos:array<int,array<string,mixed>>}
     */
    private function viaHeuristica(array $itens): array
    {
        // Tag frequency (normalized) across all items.
        $freq = [];
        foreach ($itens as $i => $item) {
            foreach ($this->tagsNormalizadas($item) as $tag) {
                $freq[$tag][] = $i;
            }
        }

        // Tags shared by 2+ items become topics, from the most common to the least.
        uasort($freq, fn ($a, $b) => count($b) <=> count($a));

        $topicos = [];
        $atribuidos = [];

        foreach ($freq as $tag => $indices) {
            $novos = array_values(array_diff($indices, $atribuidos));
            if (count($novos) < 2) {
                continue;
            }

            $topicos[] = $this->montarTopico(Str::title($tag), $novos, $itens);
            $atribuidos = array_merge($atribuidos, $novos);
        }

        // Items still without a topic → "Outros temas".
        $restantes = array_values(array_diff(array_keys($itens), $atribuidos));
        if ($restantes !== []) {
            $topicos[] = $this->montarTopico('Outros temas', $restantes, $itens);
        }

        return ['metodo' => 'heuristica', 'topicos' => $topicos];
    }

    /**
     * @param  array<int,int>  $indices
     * @param  array<int,AggregatedItem>  $itens
     * @return array{topico:string,itens:array<int,array<string,string>>,fontes:array<int,string>}
     */
    private function montarTopico(string $rotulo, array $indices, array $itens): array
    {
        $lista = [];
        $fontes = [];

        foreach ($indices as $i) {
            $item = $itens[$i];
            $lista[] = [
                'titulo' => $item->titulo,
                'url' => $item->url,
                'plataforma' => $item->plataforma,
            ];
            $fontes = array_merge($fontes, $item->fontes);
        }

        return [
            'topico' => $rotulo,
            'itens' => $lista,
            'fontes' => array_values(array_slice(array_unique($fontes), 0, 10)),
        ];
    }

    /**
     * Grouping signals of an item: its tags + the most
     * salient words of the TITLE and the TRANSCRIPT, so the topics reflect
     * what is said in the videos and not just the declared tags.
     *
     * @return array<int,string>
     */
    private function tagsNormalizadas(AggregatedItem $item): array
    {
        $tags = array_values(array_filter(array_map(
            fn ($t) => Str::of($t)->lower()->ascii()->trim()->toString(),
            $item->tags
        )));

        // Broad set of candidate words (tags + title + transcript).
        // Downstream grouping only keeps those shared by 2+ items, so
        // a broad set captures the common themes without generating noise.
        $conteudo = trim($item->titulo."\n".Str::limit($item->transcricao, 2500, ''));
        $sinais = array_values(array_unique(array_merge(
            $tags,
            array_slice($this->palavrasChave($conteudo), 0, 50)
        )));

        return $sinais !== [] ? $sinais : $this->palavrasChave($item->titulo);
    }

    /** @return array<int,string> */
    private function palavrasChave(string $texto): array
    {
        $palavras = preg_split('/[^\p{L}\p{N}]+/u', Str::ascii(Str::lower($texto))) ?: [];

        return array_values(array_unique(array_filter(
            $palavras,
            fn ($p) => Str::length($p) >= 4 && ! in_array($p, self::STOPWORDS, true)
        )));
    }

    /**
     * Tries to derive topics via LLM. Returns null if there is no key or on failure.
     *
     * @param  array<int,AggregatedItem>  $itens
     * @return array{metodo:string,topicos:array<int,array<string,mixed>>}|null
     */
    private function viaLlm(array $itens): ?array
    {
        if ($itens === [] || ! $this->llm->disponivel()) {
            return null;
        }

        $resumo = collect($itens)->map(fn (AggregatedItem $i) => [
            'titulo' => $i->titulo,
            'plataforma' => $i->plataforma,
            'url' => $i->url,
            'tags' => array_slice($i->tags, 0, 8),
            'excerto' => Str::limit(trim($i->transcricao), 1500, ''),
        ])->all();

        $idioma = ProjectLanguage::name();
        $prompt = 'Group the following content by covered topic, inferring the topic mainly from the EXCERPT of the transcript (what is actually said in the video) and the title. Respond ONLY with JSON in the format '
            .'{"topicos":[{"topico":"...","itens":[{"titulo":"...","url":"...","plataforma":"..."}]}]}. '
            ."Write the topic names in {$idioma}.\n\n"
            .json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            // Same chain as the rest of the app (Claude → GPT → DeepSeek), so the
            // topics come from the provider that is configured, not from whichever
            // key this class happened to know about.
            $json = $this->llm->texto($prompt, json: true);

            if ($json === null) {
                return null;
            }

            $dados = json_decode($this->soJson($json), true);
            $topicos = $dados['topicos'] ?? null;

            if (! is_array($topicos) || $topicos === []) {
                return null;
            }

            // Ensures the sources structure in each topic.
            foreach ($topicos as &$t) {
                $t['itens'] = array_values($t['itens'] ?? []);
                $t['fontes'] = array_values($t['fontes'] ?? []);
            }

            return ['metodo' => 'llm', 'topicos' => $topicos];
        } catch (\Throwable) {
            return null;
        }
    }

    /** The JSON object inside a reply, unwrapping any ```json fence or prose around it. */
    private function soJson(string $conteudo): string
    {
        $conteudo = trim($conteudo);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $conteudo, $m)) {
            $conteudo = trim($m[1]);
        }
        $ini = strpos($conteudo, '{');
        $fim = strrpos($conteudo, '}');

        return $ini !== false && $fim !== false && $fim > $ini ? substr($conteudo, $ini, $fim - $ini + 1) : $conteudo;
    }
}
