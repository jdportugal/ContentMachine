<?php

namespace App\Services\Editor;

use App\Services\Aggregation\LlmClient;
use Illuminate\Support\Str;

/**
 * Finds retakes: the same line delivered more than once, keeping the LAST.
 *
 * String similarity is not enough here — a retake usually starts as a stumble
 * ("the thing is— sorry — the point is…"), so the earlier attempt is a partial,
 * not a near-copy. One LLM pass over the numbered segments reads it the way a
 * person would.
 *
 * Returns removals for every attempt except the final one in each group. Never
 * throws: a failed or nonsense response means no duplicate cuts, which leaves
 * the dead-air pass and the user's own edits intact.
 */
class DuplicateTakeDetector
{
    public function __construct(private readonly LlmClient $llm) {}

    /**
     * @param  array<int,array<string,mixed>>  $segments
     * @return array<int,Removal>
     */
    public function detect(array $segments): array
    {
        $indexados = $this->indexar($segments);
        if (count($indexados) < 2 || ! $this->llm->disponivel()) {
            return [];
        }

        $resposta = $this->llm->paraPasso('editor_duplicados')->texto($this->prompt($indexados));
        if (blank($resposta)) {
            return [];
        }

        $grupos = $this->extrairGrupos((string) $resposta);
        $removals = [];

        foreach ($grupos as $grupo) {
            // Only indices we actually have, in order, and only real repeats.
            $validos = array_values(array_filter(
                array_unique(array_map('intval', $grupo)),
                fn (int $i) => isset($indexados[$i])
            ));
            sort($validos);

            if (count($validos) < 2) {
                continue;
            }

            // Retakes are stop-and-start-over, so the attempts sit together in
            // the recording. A "group" spanning distant segments is the model
            // matching topic, not takes — the exact mistake that deletes a recap
            // because it resembles the intro. Refuse it wholesale.
            foreach (array_slice($validos, 1) as $k => $i) {
                if ($i - $validos[$k] > 4) {
                    continue 2;
                }
            }

            array_pop($validos); // the last take is the keeper

            foreach ($validos as $i) {
                $seg = $indexados[$i];
                if ($seg['end'] > $seg['start']) {
                    $removals[] = new Removal(
                        $seg['start'],
                        $seg['end'],
                        Removal::DUPLICATE,
                        Str::limit($seg['text'], 60)
                    );
                }
            }
        }

        return $removals;
    }

    /**
     * @param  array<int,array<string,mixed>>  $segments
     * @return array<int,array{start:float,end:float,text:string}> keyed by index
     */
    private function indexar(array $segments): array
    {
        $out = [];

        foreach (array_values($segments) as $i => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $texto = trim((string) ($seg['text'] ?? ''));
            if ($texto === '') {
                continue;
            }
            $out[$i] = [
                'start' => (float) ($seg['start'] ?? 0),
                'end' => (float) ($seg['end'] ?? 0),
                'text' => $texto,
            ];
        }

        return $out;
    }

    /** @param array<int,array{start:float,end:float,text:string}> $segmentos */
    private function prompt(array $segmentos): string
    {
        $linhas = [];
        foreach ($segmentos as $i => $s) {
            $linhas[] = "[{$i}] ".$s['text'];
        }
        $corpo = implode("\n", $linhas);

        return <<<PROMPT
        You are editing a raw screen recording. The speaker often re-reads the same
        sentence several times until they get it right; only the LAST attempt should
        survive. Everything you group EXCEPT the last take of each group is DELETED
        from the video.

        Below is the transcript, one numbered segment per line.

        Find groups of segments that are RETAKES OF THE SAME LINE — the speaker
        saying (or trying to say) the same thing more than once.

        How a retake looks in a transcript:
        - The attempts sit NEXT TO each other (same segment or a couple apart).
          A retake means the speaker stopped and started over — the attempts are
          never far apart in the recording.
        - The attempts usually share their OPENING words: "So the model will—",
          "So the model will take your prompt and…".
        - An early attempt is often a fragment: it breaks off mid-sentence, ends in
          a stumble, filler ("uh", "wait", "sorry, again") or trails into the restart.
        - The wording may differ between attempts — group by what the speaker was
          TRYING to say, not by exact words.

        Work through the transcript in order and, for each candidate group, verify
        BOTH before including it:
        1. ADJACENT: the segments are within a few lines of each other. The same
           idea recapped later in the video (an intro restated in a summary, a
           point repeated across sections) is structure, NOT a retake — never
           group segments from different parts of the recording.
        2. REDUNDANT: if only the last take stays, no information is lost. If an
           earlier "attempt" contains anything the last one does not, it is not a
           retake — leave it alone.

        Do NOT group:
        - sentences that merely share a topic or a few words,
        - a phrase deliberately repeated for emphasis,
        - a recap, summary or callback of something said earlier,
        - normal speech that happens to be similar.

        Respond with ONLY a JSON object, no prose:
        {"groups": [{"segments": [3, 4, 5], "line": "the sentence being re-attempted"}]}

        `segments` lists the segment numbers of one retake group in order — the
        LAST number is the take that will be kept. `line` is a short quote of the
        line they all attempt; if you cannot quote one line the group is wrong.
        Return {"groups": []} if there are none.

        TRANSCRIPT:
        {$corpo}
        PROMPT;
    }

    /** @return array<int,array<int,int>> */
    private function extrairGrupos(string $resposta): array
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
        if (! is_array($dados) || ! is_array($dados['groups'] ?? null)) {
            return [];
        }

        // Either shape: [{"segments":[3,4],"line":"…"}] or the bare [[3,4]].
        return array_values(array_filter(array_map(
            fn ($g) => is_array($g['segments'] ?? null) ? $g['segments'] : $g,
            array_filter($dados['groups'], 'is_array')
        ), 'is_array'));
    }
}
