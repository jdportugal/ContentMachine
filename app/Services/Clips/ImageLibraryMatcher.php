<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Matches the planner's image suggestions to images already in the project's
 * ImageLibrary, so a scene reuses an existing asset (a logo, a brand shot) instead
 * of asking the user or generating one. One LLM pass returns, per suggestion, the
 * best-fitting library image id or nothing — only confident matches are kept.
 */
class ImageLibraryMatcher
{
    use RunsClaudeCli;

    /**
     * @param  array<int,array{key:string,prompt:string}>  $requests  the pending image suggestions
     * @param  array<int,array{id:string,name:string,description:string}>  $library  available library images
     * @return array<string,string>  requestKey => libraryImageId (matches only)
     */
    public function match(array $requests, array $library): array
    {
        if ($requests === [] || $library === []) {
            return [];
        }

        $needed = collect($requests)
            ->map(fn ($r) => "- key {$r['key']}: {$r['prompt']}")
            ->implode("\n");
        $available = collect($library)
            ->map(fn ($i) => "- id {$i['id']}: {$i['name']}".($i['description'] && $i['description'] !== $i['name'] ? " — {$i['description']}" : ''))
            ->implode("\n");

        $prompt = <<<PROMPT
You match images a short video NEEDS to images that already EXIST in a library.
Only match when the library image clearly and specifically depicts what the
suggestion asks for (e.g. the exact brand, logo, product or person). When in
doubt, DO NOT match — a wrong image is worse than a generated one.

NEEDED images (key: description):
{$needed}

LIBRARY images (id: name — description):
{$available}

Return ONLY JSON, no markdown:
{"matches": {"<needed key>": "<library id>", ...}}
Include only confident matches; omit keys with no good match. {} if none.
PROMPT;

        try {
            $envelope = $this->runClaude($prompt, null, ['maxTurns' => 1]);
        } catch (\Throwable $e) {
            Log::warning('Image library match failed: '.$e->getMessage());

            return [];
        }

        $matches = $this->extractJson((string) ($envelope['result'] ?? ''))['matches'] ?? [];
        if (! is_array($matches)) {
            return [];
        }

        // Keep only real (key, id) pairs — the model can hallucinate either side.
        $validKeys = array_column($requests, 'key');
        $validIds = array_column($library, 'id');
        $out = [];
        foreach ($matches as $key => $id) {
            if (is_string($key) && is_string($id) && in_array($key, $validKeys, true) && in_array($id, $validIds, true)) {
                $out[$key] = $id;
            }
        }

        return $out;
    }

    /** Pull the first JSON object out of the model's reply. */
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
}
