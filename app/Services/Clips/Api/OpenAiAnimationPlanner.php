<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\AnimationPlanner;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiAnimationPlanner implements AnimationPlanner
{
    private const PRIMITIVES = [
        'fade', 'slide', 'scale', 'kinetic-text', 'highlight', 'fleuron-draw',
        'seal-stamp', 'underline-sweep', 'count-up', 'image-reveal', 'ambient',
    ];

    public function plan(array $transcript, string $mode, array $options = []): array
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY em falta para o planeador.');
        }

        $width = $options['width'] ?? config('contentmachine.clips.width');
        $height = $options['height'] ?? config('contentmachine.clips.height');
        $fps = $options['fps'] ?? config('contentmachine.clips.fps');
        $duration = (float) ($transcript['duration'] ?? 0.0);

        $response = Http::withToken($key)
            ->timeout(180)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => config('contentmachine.clips.openai_model'),
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.5,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($mode)],
                    ['role' => 'user', 'content' => $this->userPrompt($transcript, $mode, $duration)],
                ],
            ])
            ->throw()
            ->json();

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $decoded = json_decode($content, true) ?: [];

        return [
            'duration' => $duration,
            'mode' => $mode,
            'width' => $width,
            'height' => $height,
            'fps' => $fps,
            'transparent' => $mode === 'sparse',
            'animations' => $this->sanitize($decoded['animations'] ?? []),
        ];
    }

    private function systemPrompt(string $mode): string
    {
        $style = @file_get_contents(config('contentmachine.clips.style_md')) ?: '';
        $vocab = implode(', ', self::PRIMITIVES);
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

=== MANUAL DE ESTILO (estilo-animacao.md) ===
{$style}
PROMPT;
    }

    private function userPrompt(array $transcript, string $mode, float $duration): string
    {
        $words = json_encode($transcript['words'] ?? [], JSON_UNESCAPED_UNICODE);

        return "Duração total: {$duration}s. Modo: {$mode}.\n"
            ."Transcrição (palavras com timestamps): {$words}\n"
            .'Devolve o plano de animações em JSON.';
    }

    private function sanitize(array $animations): array
    {
        $out = [];
        foreach ($animations as $a) {
            if (! isset($a['start'], $a['end'], $a['primitive'])) {
                continue;
            }
            if (! in_array($a['primitive'], self::PRIMITIVES, true)) {
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
}
