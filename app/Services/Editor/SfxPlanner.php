<?php

namespace App\Services\Editor;

use App\Services\Aggregation\LlmClient;
use Illuminate\Support\Str;

/**
 * Reads the FINAL cut's transcript and proposes where a visual effect would
 * earn its place — a number being said, a list being enumerated, a comparison.
 *
 * Times are on the EDITED timeline, so the planner is given the transcript after
 * the cuts have been applied; feeding it the raw one would place every effect
 * late by the amount removed before it.
 *
 * Never throws: no plan simply means no effects offered.
 */
class SfxPlanner
{
    /** Keeps one pass from proposing a wall of effects. */
    private const MAX = 8;

    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  array<int,array{start:float,end:float,text:string}>  $segments  edited-timeline segments
     * @return array<int,array{at:float,duration:float,brief:string,text:string}>
     */
    public function plan(array $segments, int $limite = 5): array
    {
        $segments = array_values(array_filter($segments, fn ($s) => is_array($s) && trim((string) ($s['text'] ?? '')) !== ''));
        if ($segments === [] || ! $this->llm->disponivel()) {
            return [];
        }

        $resposta = $this->llm->paraPasso('editor_sfx')->texto($this->prompt($segments, min($limite, self::MAX)));
        if (blank($resposta)) {
            return [];
        }

        $propostas = $this->extrair((string) $resposta);
        $out = [];

        foreach ($propostas as $p) {
            $i = (int) ($p['segment'] ?? -1);
            $brief = trim((string) ($p['brief'] ?? ''));
            if (! isset($segments[$i]) || $brief === '') {
                continue;
            }

            $seg = $segments[$i];
            $inicio = (float) ($seg['start'] ?? 0);
            $fim = (float) ($seg['end'] ?? 0);

            $out[] = [
                'at' => round($inicio, 2),
                // Cover the line being spoken, within sane bounds.
                'duration' => round(max(2.0, min(6.0, $fim - $inicio)), 2),
                'brief' => Str::limit($brief, 300, ''),
                'text' => trim((string) ($seg['text'] ?? '')),
            ];
        }

        // One effect per moment, earliest first.
        usort($out, fn ($a, $b) => $a['at'] <=> $b['at']);

        return array_slice($out, 0, self::MAX);
    }

    /** @param array<int,array<string,mixed>> $segments */
    private function prompt(array $segments, int $limite): string
    {
        $linhas = [];
        foreach ($segments as $i => $s) {
            $linhas[] = sprintf('[%d] %s', $i, trim((string) ($s['text'] ?? '')));
        }
        $corpo = implode("\n", $linhas);

        return <<<PROMPT
        You choose where a VISUAL effect should appear over a screen recording.

        Below is the transcript of the finished edit, one numbered segment per line.

        Pick at most {$limite} segments where an on-screen graphic would genuinely
        help the viewer — a figure or statistic being said, a short list being
        enumerated, a before/after comparison, a key term being defined, a step in a
        process. Skip filler, greetings and anything already obvious on screen.

        For each, write a ONE-LINE brief describing the animation to build. Describe
        motion and content, not colours or fonts — those come from the brand's
        design system.

        Respond with ONLY a JSON object, no prose:
        {"effects": [{"segment": 4, "brief": "the number 320 counts up and locks in with a soft flash"}]}

        Return {"effects": []} if nothing warrants one.

        TRANSCRIPT:
        {$corpo}
        PROMPT;
    }

    /** @return array<int,array<string,mixed>> */
    private function extrair(string $resposta): array
    {
        $texto = trim($resposta);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $texto, $m)) {
            $texto = trim($m[1]);
        }
        $inicio = strpos($texto, '{');
        $fim = strrpos($texto, '}');
        if ($inicio === false || $fim === false || $fim <= $inicio) {
            return [];
        }

        $dados = json_decode(substr($texto, $inicio, $fim - $inicio + 1), true);
        if (! is_array($dados) || ! is_array($dados['effects'] ?? null)) {
            return [];
        }

        return array_values(array_filter($dados['effects'], 'is_array'));
    }
}
