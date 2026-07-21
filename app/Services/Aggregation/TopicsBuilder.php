<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Deriva uma lista de tópicos ("o que já foi coberto e está no ar") a partir
 * de um conjunto de itens de um dia. Usa um LLM se houver chave configurada
 * (OpenAI ou Gemini); caso contrário, recorre a uma heurística determinística
 * de agrupamento por tags/palavras-chave. Nunca lança — degrada para heurística.
 */
class TopicsBuilder
{
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
        // Frequência de tags (normalizadas) entre todos os itens.
        $freq = [];
        foreach ($itens as $i => $item) {
            foreach ($this->tagsNormalizadas($item) as $tag) {
                $freq[$tag][] = $i;
            }
        }

        // Tags partilhadas por 2+ itens tornam-se tópicos, das mais comuns às menos.
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

        // Itens ainda sem tópico → "Outros temas".
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

    /** @return array<int,string> */
    private function tagsNormalizadas(AggregatedItem $item): array
    {
        $tags = array_map(fn ($t) => Str::of($t)->lower()->trim()->toString(), $item->tags);
        $tags = array_values(array_filter($tags));

        if ($tags !== []) {
            return array_unique($tags);
        }

        // Sem tags: extrai palavras-chave do título (sem stopwords).
        return $this->palavrasChave($item->titulo);
    }

    /** @return array<int,string> */
    private function palavrasChave(string $texto): array
    {
        $stop = ['the', 'and', 'for', 'you', 'your', 'with', 'this', 'that', 'from', 'como', 'para', 'uma', 'que', 'dos', 'das', 'com', 'por'];
        $palavras = preg_split('/[^\p{L}\p{N}]+/u', Str::lower($texto)) ?: [];

        return array_values(array_unique(array_filter(
            $palavras,
            fn ($p) => Str::length($p) >= 4 && ! in_array($p, $stop, true)
        )));
    }

    /**
     * Tenta derivar tópicos via LLM. Devolve null se não houver chave ou em falha.
     *
     * @param  array<int,AggregatedItem>  $itens
     * @return array{metodo:string,topicos:array<int,array<string,mixed>>}|null
     */
    private function viaLlm(array $itens): ?array
    {
        $chaveOpenai = config('services.openai.key');
        $chaveGemini = config('services.gemini.key');

        if (empty($chaveOpenai) && empty($chaveGemini) || $itens === []) {
            return null;
        }

        $resumo = collect($itens)->map(fn (AggregatedItem $i) => [
            'titulo' => $i->titulo,
            'plataforma' => $i->plataforma,
            'url' => $i->url,
            'tags' => array_slice($i->tags, 0, 8),
        ])->all();

        $prompt = 'Agrupa os seguintes conteúdos por tópico coberto. Responde SÓ com JSON no formato '
            .'{"topicos":[{"topico":"...","itens":[{"titulo":"...","url":"...","plataforma":"..."}]}]}. '
            ."Usa português europeu nos nomes dos tópicos.\n\n"
            .json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        try {
            $json = ! empty($chaveOpenai)
                ? $this->chamarOpenai((string) $chaveOpenai, $prompt)
                : $this->chamarGemini((string) $chaveGemini, $prompt);

            if ($json === null) {
                return null;
            }

            $dados = json_decode($json, true);
            $topicos = $dados['topicos'] ?? null;

            if (! is_array($topicos) || $topicos === []) {
                return null;
            }

            // Garante a estrutura de fontes em cada tópico.
            foreach ($topicos as &$t) {
                $t['itens'] = array_values($t['itens'] ?? []);
                $t['fontes'] = array_values($t['fontes'] ?? []);
            }

            return ['metodo' => 'llm', 'topicos' => $topicos];
        } catch (\Throwable) {
            return null;
        }
    }

    private function chamarOpenai(string $chave, string $prompt): ?string
    {
        $r = Http::timeout(60)
            ->withToken($chave)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('contentmachine.aggregation.openai_model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2,
            ]);

        return $r->successful() ? ($r->json('choices.0.message.content') ?? null) : null;
    }

    private function chamarGemini(string $chave, string $prompt): ?string
    {
        $modelo = (string) config('contentmachine.aggregation.gemini_model', 'gemini-1.5-flash');
        $r = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$chave}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['responseMimeType' => 'application/json'],
            ]);

        return $r->successful() ? ($r->json('candidates.0.content.parts.0.text') ?? null) : null;
    }
}
