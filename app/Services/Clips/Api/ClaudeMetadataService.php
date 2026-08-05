<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\MetadataService;
use App\Services\Projects\ProjectLanguage;

/**
 * Suggest a title, description and tags for a clip from its transcript, via the
 * authenticated `claude` CLI (uses the subscription, not per-token API billing).
 */
class ClaudeMetadataService implements MetadataService
{
    use RunsClaudeCli;

    public function suggest(array $transcript): array
    {
        $text = trim((string) ($transcript['text'] ?? ''));
        $language = ProjectLanguage::name();
        if ($text === '') {
            return $this->empty();
        }

        $prompt = <<<PROMPT
From the spoken text below, suggest ready-to-publish metadata for a
short vertical video, in the {$language} language.

Return ONLY JSON (no markdown):
{
  "title": "short and captivating title (max. 70 characters)",
  "description": "2 to 3 sentence description, ready to publish, no hashtags",
  "tags": ["tag1", "tag2", "…"]
}
5 to 8 relevant tags, without the # symbol.

SPOKEN TEXT:
{$text}
PROMPT;

        $envelope = $this->runClaude($prompt, null, ['maxTurns' => 1]);

        return $this->normalize($this->extractJson((string) ($envelope['result'] ?? '')));
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

    private function normalize(array $m): array
    {
        $tags = is_array($m['tags'] ?? null) ? $m['tags'] : [];
        $tags = array_values(array_filter(array_map(
            fn ($t) => ltrim(trim((string) $t), '#'),
            $tags
        )));

        return [
            'title' => trim((string) ($m['title'] ?? '')),
            'description' => trim((string) ($m['description'] ?? '')),
            'tags' => $tags,
        ];
    }

    private function empty(): array
    {
        return ['title' => '', 'description' => '', 'tags' => []];
    }
}
