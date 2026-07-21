<?php

namespace App\Services\Clips\Api;

/**
 * Shared prompt-building + output-sanitizing for LLM animation planners
 * (OpenAI and Claude), so both stay in lock-step with the style md and vocab.
 */
trait BuildsAnimationPrompt
{
    protected array $primitives = [
        'fade', 'slide', 'scale', 'kinetic-text', 'highlight', 'fleuron-draw',
        'seal-stamp', 'underline-sweep', 'count-up', 'image-reveal', 'ambient',
    ];

    protected function systemPrompt(string $mode): string
    {
        $style = @file_get_contents(config('contentmachine.clips.style_md')) ?: '';
        $vocab = implode(', ', $this->primitives);
        $rule = $mode === 'dense'
            ? 'MODO DENSE: a linha temporal TEM de cobrir 100% da duração — cada segundo tem uma animação, sem lacunas.'
            : 'MODO SPARSE: animações APENAS nos momentos que valem a pena (números, nomes, viragens). Lacunas são desejáveis.';

        return <<<PROMPT
És o planeador de animações do estúdio IATECA. Segues à risca o manual de estilo abaixo.
Devolves SEMPRE um objecto JSON com a chave "animations": uma lista de objectos
{start, end, primitive, text, params}. Os tempos são em segundos (float).
O campo "primitive" só pode ser um de: {$vocab}.
{$rule}
Alinha os tempos às palavras da transcrição. Não inventes primitivas fora da lista.
Não incluas explicações nem markdown — apenas o objecto JSON.

=== MANUAL DE ESTILO (estilo-animacao.md) ===
{$style}
PROMPT;
    }

    protected function userPrompt(array $transcript, string $mode, float $duration): string
    {
        $words = json_encode($transcript['words'] ?? [], JSON_UNESCAPED_UNICODE);

        return "Duração total: {$duration}s. Modo: {$mode}.\n"
            ."Transcrição (palavras com timestamps): {$words}\n"
            .'Devolve o plano de animações em JSON.';
    }

    protected function envelope(array $transcript, string $mode, array $options, array $animations): array
    {
        return [
            'duration' => (float) ($transcript['duration'] ?? 0.0),
            'mode' => $mode,
            'width' => $options['width'] ?? config('contentmachine.clips.width'),
            'height' => $options['height'] ?? config('contentmachine.clips.height'),
            'fps' => $options['fps'] ?? config('contentmachine.clips.fps'),
            'transparent' => $mode === 'sparse',
            'animations' => $this->sanitize($animations),
        ];
    }

    protected function sanitize(array $animations): array
    {
        $out = [];
        foreach ($animations as $a) {
            if (! isset($a['start'], $a['end'], $a['primitive'])) {
                continue;
            }
            if (! in_array($a['primitive'], $this->primitives, true)) {
                continue;
            }
            $out[] = [
                'start' => (float) $a['start'],
                'end' => (float) $a['end'],
                'primitive' => $a['primitive'],
                'text' => $a['text'] ?? null,
                'params' => $a['params'] ?? [],
            ];
        }

        return $out;
    }

    /** Extract a JSON object from model output that may be fenced or prefixed. */
    protected function extractJson(string $content): array
    {
        $content = trim($content);
        // strip ```json ... ``` fences if present
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) {
            $content = trim($m[1]);
        }
        // fall back to the outermost { ... }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }

        return json_decode($content, true) ?: [];
    }
}
