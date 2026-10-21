<?php

namespace App\Services\RealEstate;

use App\Services\Clips\Api\RunsClaudeCli;

/**
 * Which of a listing's video clips to show while each part of the voiceover is
 * said. One call splits the voiceover, word by word, into topics — each a thing
 * to show ("…e uma | cozinha equipada | Pavimento radiante…") — and ranks the
 * clips that show it; the plan then lays the footage on those topics
 * (SceneVisualFiller::footageTimeline / syncVideoClips / fitVideoScenes).
 */
class VideoClipMatcher
{
    use RunsClaudeCli;

    protected function passo(): string
    {
        return 'clips_plano';
    }

    /**
     * @param  list<array{word?:string,start:float,end:float}>  $words  the voiceover's words
     * @param  list<array{id:string,description:string}>  $clips
     * @param  array{at:float,seconds:float}|null  $gap  an after-intro wait: later words move back by it
     * @return list<array{start:float,end:float,text:string,clips:list<string>}> in the plan's timeline
     */
    public function segments(array $words, array $clips, ?array $gap = null): array
    {
        $words = array_values($words);
        if ($words === [] || $clips === []) {
            return [];
        }
        $wordList = collect($words)->map(fn ($w, $i) => ($i + 1).':'.trim((string) ($w['word'] ?? '')))->implode(' ');
        $clipList = collect($clips)->map(fn ($c) => "- {$c['id']}: {$c['description']}")->implode("\n");

        $prompt = <<<PROMPT
        A real estate reel shows short video clips of the property while a voiceover is read; the viewer must SEE what is
        being said, when it is said. Split the voiceover (numbered words below) into consecutive topics, each one thing to
        show: a new topic starts at the word where the voiceover moves on to something else to show — "…roupeiros
        embutidos e uma | cozinha equipada | Pavimento radiante…" — even mid-sentence. Topics of at least ~3 words.
        For each topic list the clips that SHOW it, best first — every clip that fits, up to 5 (several clips of one room
        are other angles: list them all, so nothing has to repeat). "dois quartos" → the bedrooms; "cozinha equipada" →
        the kitchen; "vista sobre o mar / o Atlântico / a praia" → clips whose description shows the sea; the town, the
        beach or the area → the views and the outside; finishes in every room → the best-looking rooms. Never a clip
        whose description doesn't show it (rooftops are not a sea view). Only the price, the contact or a line about the
        agent gets [].

        CLIPS:
        {$clipList}

        VOICEOVER WORDS:
        {$wordList}

        Return ONLY JSON: {"topics":[{"from":1,"clips":["vid_…"]}]}  ("from" = the topic's first word number)
        PROMPT;

        $out = (string) ($this->runClaude($prompt, null, ['maxTurns' => 1, 'timeout' => 300])['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;

        return self::toSegments($words, (array) ($json['topics'] ?? []), array_column($clips, 'id'), $gap);
    }

    /**
     * Topics (first word number, clips) → timed segments: each from its first
     * word to the next topic's first word (the last to the last word).
     *
     * @param  list<array{from:int,clips:list<string>}>  $topics
     * @param  string[]  $ids  the clips that exist
     * @return list<array{start:float,end:float,text:string,clips:list<string>}>
     */
    public static function toSegments(array $words, array $topics, array $ids, ?array $gap = null): array
    {
        $shift = fn (float $t) => $gap && $t >= (float) $gap['at'] - 1e-6 ? $t + (float) $gap['seconds'] : $t;
        $starts = collect($topics)->map(fn ($t) => ['from' => max(0, (int) ($t['from'] ?? 1) - 1), 'clips' => array_values(array_intersect((array) ($t['clips'] ?? []), $ids))])
            ->filter(fn ($t) => $t['from'] < count($words))->sortBy('from')->unique('from')->values();
        if ($starts->isEmpty() || $starts[0]['from'] > 0) {
            $starts->prepend(['from' => 0, 'clips' => []]);
        }

        return $starts->values()->map(function ($t, $k) use ($starts, $words, $shift) {
            $last = ($starts[$k + 1]['from'] ?? count($words)) - 1;

            return [
                'start' => round($shift((float) $words[$t['from']]['start']), 2),
                'end' => round($shift((float) $words[$last]['end']), 2),
                'text' => trim(implode(' ', array_map(fn ($w) => trim((string) ($w['word'] ?? '')), array_slice($words, $t['from'], $last - $t['from'] + 1)))),
                'clips' => $t['clips'],
            ];
        })->all();
    }
}
