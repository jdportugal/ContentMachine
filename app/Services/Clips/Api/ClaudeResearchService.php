<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\ClipLanguage;
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
        $language = ClipLanguage::name();
        if ($text === '') {
            return $this->empty();
        }

        $prompt = <<<PROMPT
Search the WEB about the topic of the following spoken text and gather REAL and CURRENT FACTS
to enrich a short vertical video. Do SEVERAL searches.

VERIFICATION (mandatory):
- Confirm EACH number/fact in at least TWO independent and recent sources.
- Only include data you have HIGH confidence in and with a source URL. When in doubt, OMIT.
- Never invent numbers or dates. Prefer exact and current values; state the period in the label.

Return ONLY JSON (no markdown), short labels, in the {$language} language:
{
  "topic": "…",
  "summary": "1-2 sentences",
  "timeline": [{"label":"…","sublabel":"year/period"}],
  "stats": [{"label":"…","value":<number>,"unit":"…"}],
  "comparisons": [{"title":"…","left":{"title":"…","points":["…"]},"right":{"title":"…","points":["…"]}}],
  "keyPoints": ["…","…"],
  "sources": ["url","url"]
}
Leave lists empty if there is no verifiable data.

SPOKEN TEXT:
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
