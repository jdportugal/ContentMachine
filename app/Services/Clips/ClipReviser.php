<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use RuntimeException;

/**
 * "Improve with AI": a clip's script reworked as the user asks, and what the
 * planner should do differently (which photos and footage, timing, effects).
 * No picture is generated — the clip is re-voiced and re-planned from this.
 */
class ClipReviser
{
    use RunsClaudeCli;

    protected function passo(): string
    {
        return 'clips_plano';
    }

    /**
     * @param  bool  $canRewrite  the voice is generated (a recorded one can't change: direction only)
     * @param  string  $keep  a line that must stay word for word (the after-intro line)
     * @return array{voiceover:string,direction:string} voiceover '' = keep the current one
     */
    public function revise(string $script, string $request, string $context = '', bool $canRewrite = true, string $keep = ''): array
    {
        $rewrite = $canRewrite
            ? '"voiceover": the script rewritten to do what the request asks about what is SAID (in the same language and register, European Portuguese if it is in Portuguese; keep every fact from the current script and the context unless the request says to drop it; what the request itself states — a dam nearby, the river beaches — may be said; about the same length unless asked otherwise; no placeholders, numbers as they are spoken)'
                .($keep !== '' ? ". It must still contain this line word for word, right after the opening sentence: «{$keep}»" : '')
                .'. Return "" if the request is only about the visuals.'
            : '"voiceover": "" (the voice is a recording: it cannot change).';

        $prompt = <<<PROMPT
        A short vertical real estate reel (a voiceover over photos and/or video footage, with animated effects) is being
        reworked. The user asks:
        «{$request}»

        CURRENT SCRIPT:
        {$script}

        {$context}

        Return ONLY JSON:
        {
          {$rewrite},
          "direction": "in English, short and concrete, what the video planner must do differently to honour the request: which photos or footage to show more or less (by what they show), pacing and cut length, which kinds of effects; \"\" if nothing"
        }
        PROMPT;

        $out = (string) ($this->runClaude($prompt, null, ['maxTurns' => 1, 'timeout' => 300])['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;
        if (! is_array($json)) {
            throw new RuntimeException('The AI did not return a revision.');
        }
        $voiceover = $canRewrite ? trim((string) ($json['voiceover'] ?? '')) : '';
        if (preg_match('/\[[^\]]*\]/u', $voiceover)) {
            throw new RuntimeException('The revised script kept a placeholder: '.mb_substr($voiceover, 0, 120));
        }

        return ['voiceover' => $voiceover, 'direction' => trim((string) ($json['direction'] ?? ''))];
    }
}
