<?php

namespace App\Services\Ferramentas;

use App\Models\FerramentaIa;
use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Imoplanta\ImoplantaCustos;
use Illuminate\Support\Facades\Storage;

/**
 * Looks at the customer's photo before anything is spent: is it a good start for this tool (Realizador::PROMPTS
 * [tool]['analise'])? Not a gate: a photo that isn't ideal only gets a warning, and the customer decides. Its
 * resolution is measured here; the rest is one vision-LLM call (the plan reader's provider chain).
 */
class Analista
{
    use RunsClaudeCli;

    /** Below this many pixels on the longest side the AI frames come out soft. */
    public const LADO_MINIMO = 1200;

    public function __construct(private ImoplantaCustos $custos) {}

    protected function passo(): string
    {
        return 'imoplanta_modelo';
    }

    /** @return array{estado:string,adequada:bool,problemas:list<string>,dica:string} */
    public function analisar(FerramentaIa $f): array
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        [$w, $h] = getimagesizefromstring($disk->get($f->original)) ?: [0, 0];
        $problemas = max($w, $h) < self::LADO_MINIMO ? ['A fotografia tem pouca resolução ('.$w.'×'.$h.' px): use o original, com pelo menos '.self::LADO_MINIMO.' px de largura.'] : [];

        $prompt = Realizador::PROMPTS[$f->ferramenta]['analise']."\n\n".<<<'TXT'
        Answer with ONLY this JSON, nothing else:
        {"adequada": true|false, "problemas": ["…"], "dica": "…"}
        - adequada: false only when a problem would clearly hurt the video.
        - problemas: at most 3 short sentences in European Portuguese, for the customer (an estate agent), plain words, no jargon; [] if none.
        - dica: one sentence in European Portuguese saying which photo would work better; "" when adequada is true.
        TXT;
        $envelope = $this->runClaude($prompt, null, ['images' => [$disk->path($f->original)], 'maxTurns' => 3, 'timeout' => 120]);
        $this->custos->llm($envelope);
        $json = preg_match('/\{.*\}/s', (string) ($envelope['result'] ?? ''), $m) ? json_decode($m[0], true) : null;
        if (! is_array($json) || ! array_key_exists('adequada', $json)) {
            throw new \RuntimeException('A análise não devolveu JSON.');
        }
        $problemas = array_slice([...$problemas, ...array_values(array_filter(array_map('strval', (array) ($json['problemas'] ?? []))))], 0, 4);

        return [
            'estado' => 'feito',
            'adequada' => (bool) $json['adequada'] && max($w, $h) >= self::LADO_MINIMO,
            'problemas' => $problemas,
            'dica' => (string) ($json['dica'] ?? ''),
        ];
    }
}
