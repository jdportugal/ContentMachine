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
        survive.

        Below is the transcript, one numbered segment per line.

        Find groups of segments that are RETAKES OF THE SAME LINE — the speaker
        saying (or trying to say) the same thing more than once. Include false
        starts and stumbles that were then restarted, even when the wording differs.

        Do NOT group:
        - sentences that merely share a topic or a few words,
        - a phrase deliberately repeated for emphasis,
        - normal speech that happens to be similar.

        When unsure, leave it out. A missed retake costs a second of video; a wrong
        one deletes something the speaker meant to keep.

        Respond with ONLY a JSON object, no prose:
        {"groups": [[3,4,5], [11,12]]}

        Each inner array lists the segment numbers of one retake group, in order.
        The LAST number in each group is the take that will be kept. Return
        {"groups": []} if there are none.

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

        return array_values(array_filter($dados['groups'], 'is_array'));
    }
}
