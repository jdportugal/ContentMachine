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

    protected function passo(): string
    {
        return 'clips_metadados';
    }

    public function suggest(array $transcript, string $context = ''): array
    {
        $text = trim((string) ($transcript['text'] ?? ''));
        $language = ProjectLanguage::name();
        if ($text === '') {
            return $this->empty();
        }

        $prompt = <<<PROMPT
From the spoken text below, write ready-to-publish metadata for a short
vertical video (Instagram Reels, TikTok, YouTube Shorts), in the {$language} language.

Return ONLY JSON (no markdown):
{
  "title": "short and captivating title (max. 70 characters)",
  "description": "the post caption, formatted for social media (rules below)",
  "tags": ["tag1", "tag2", "…"]
}

Caption rules:
- Line 1 is the hook: one short line that stops the scroll (it is all people see before "more").
  Make it specific to THIS video (a concrete detail, number or place), never a generic opener.
- Then the substance, in short lines separated with \\n (JSON newlines): give real information people
  would ask about — use the FACTS below when given (typology, areas, price, location and what is nearby,
  condition, energy rating, standout features). Vary the form: a few short lines, a short paragraph, or a
  compact facts line ("T3 · 222 m² · 350.000 €"), whatever suits this video.
- End with a short call to action on its own line. If there is a name and phone number to contact,
  put both on that line.
- Blank line (\\n\\n) between the hook, the body and the call to action. No hashtags in the caption.
- Emojis: at most 3 in the whole caption, only where they help; zero is fine. Never one per line.
- Use only facts from the spoken text and the FACTS below.
- Do not repeat the project's RECENT CAPTIONS below: no same opening words, structure or emoji set.

Tags: 8 to 12, without the # symbol, each a single word with no spaces (join words: "casasemlisboa"),
lowercase, mixing broad ones with niche and location-specific ones.

{$context}

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
            fn ($t) => preg_replace('/\s+/u', '', ltrim(trim((string) $t), '#')),
            $tags
        )));

        return [
            'title' => trim((string) ($m['title'] ?? '')),
            'description' => self::fewEmojis(trim((string) ($m['description'] ?? ''))),
            'tags' => $tags,
        ];
    }

    /** At most $max emojis: the ones after are dropped (the prompt asks; this makes sure). */
    public static function fewEmojis(string $text, int $max = 3): string
    {
        $n = 0;

        return preg_replace_callback('/\p{Extended_Pictographic}[\x{FE0F}\x{200D}\p{Extended_Pictographic}]*\s?/u', function ($m) use (&$n, $max) {
            return ++$n <= $max ? $m[0] : '';
        }, $text);
    }

    private function empty(): array
    {
        return ['title' => '', 'description' => '', 'tags' => []];
    }
}
