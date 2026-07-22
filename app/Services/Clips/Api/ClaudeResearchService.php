<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\ResearchService;

/**
 * Deep-research the transcript's topic via the `claude` CLI with web search,
 * returning structured facts to enrich the video's visualizations.
 */
class ClaudeResearchService implements ResearchService
{
    use RunsClaudeCli;

    public function research(array $transcript): array
    {
        $text = trim((string) ($transcript['text'] ?? ''));
        $language = $transcript['language'] ?? '(o idioma do texto)';
        if ($text === '') {
            return $this->empty();
        }

        $prompt = <<<PROMPT
Pesquisa na WEB sobre o tópico do seguinte texto falado e reúne FACTOS REAIS e ACTUAIS
para enriquecer um vídeo curto vertical. Faz VÁRIAS pesquisas.

VERIFICAÇÃO (obrigatória):
- Confirma CADA número/facto em pelo menos DUAS fontes independentes e recentes.
- Só inclui dados de que tens ALTA confiança e com URL de fonte. Em caso de dúvida, OMITE.
- Nunca inventes números nem datas. Prefere valores exactos e actuais; indica a época no rótulo.

Devolve APENAS JSON (sem markdown), rótulos curtos, no idioma {$language}:
{
  "topic": "…",
  "summary": "1-2 frases",
  "timeline": [{"label":"…","sublabel":"ano/época"}],
  "stats": [{"label":"…","value":<número>,"unit":"…"}],
  "comparisons": [{"title":"…","left":{"title":"…","points":["…"]},"right":{"title":"…","points":["…"]}}],
  "keyPoints": ["…","…"],
  "sources": ["url","url"]
}
Deixa listas vazias se não houver dados verificáveis.

TEXTO FALADO:
{$text}
PROMPT;

        $envelope = $this->runClaude($prompt, null, [
            'allowedTools' => 'WebSearch',
            'timeout' => 300,
        ]);

        $facts = $this->extractJson((string) ($envelope['result'] ?? ''));

        return $this->normalize($facts);
    }

    private function extractJson(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) {
            $content = trim($m[1]);
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }

        return json_decode($content, true) ?: [];
    }

    private function normalize(array $f): array
    {
        return [
            'topic' => (string) ($f['topic'] ?? ''),
            'summary' => (string) ($f['summary'] ?? ''),
            'timeline' => is_array($f['timeline'] ?? null) ? array_values($f['timeline']) : [],
            'stats' => is_array($f['stats'] ?? null) ? array_values($f['stats']) : [],
            'comparisons' => is_array($f['comparisons'] ?? null) ? array_values($f['comparisons']) : [],
            'keyPoints' => is_array($f['keyPoints'] ?? null) ? array_values($f['keyPoints']) : [],
            'sources' => is_array($f['sources'] ?? null) ? array_values($f['sources']) : [],
        ];
    }

    private function empty(): array
    {
        return ['topic' => '', 'summary' => '', 'timeline' => [], 'stats' => [], 'comparisons' => [], 'keyPoints' => [], 'sources' => []];
    }
}
