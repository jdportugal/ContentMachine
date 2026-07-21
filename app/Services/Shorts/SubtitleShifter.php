<?php

namespace App\Services\Shorts;

/**
 * Porta para PHP da lógica do nó "Prepare Clip Subtitles" do fluxo n8n
 * "Long to shorts": recorta a transcrição completa do vídeo para a janela
 * de um clip e desloca todos os tempos (segmento + palavra) para começarem
 * em zero.
 *
 * Sem dependências externas — é o núcleo testável do pipeline.
 */
class SubtitleShifter
{
    /**
     * Converte um instante em segundos (float).
     *
     * Aceita, tal como o Flask `parse_time_to_seconds`:
     *   - número (int|float) → segundos;
     *   - string numérica "12.5" → segundos;
     *   - "HH:MM:SS" → h*3600 + m*60 + s;
     *   - "HH:MM:SS[,.:]mmm" com 1–3 dígitos de milissegundos.
     *
     * NOTA de paridade: os milissegundos são preenchidos à DIREITA até 3
     * dígitos (":5" → ".500", não ".005"), replicando o `ljust(3, '0')` do
     * servidor. Use sempre 3 dígitos para evitar surpresas.
     */
    public static function parseTimeToSeconds(int|float|string|null $input): float
    {
        if ($input === null || $input === '') {
            return 0.0;
        }

        if (is_int($input) || is_float($input)) {
            return (float) $input;
        }

        $s = trim((string) $input);

        if (is_numeric($s)) {
            return (float) $s;
        }

        // HH:MM:SS com milissegundos (separador , . ou :) e 1–3 dígitos.
        if (preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})[,.:]\s*(\d{1,3})$/', $s, $m)) {
            $ms = str_pad($m[4], 3, '0', STR_PAD_RIGHT);

            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3] + (int) $ms / 1000.0;
        }

        // HH:MM:SS sem milissegundos.
        if (preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $s, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }

        return 0.0;
    }

    /**
     * Formata segundos como "HH:MM:SS.mmm" (útil para chamar /split-video).
     */
    public static function secondsToTimestamp(float $seconds): string
    {
        $seconds = max(0.0, $seconds);
        $whole = (int) floor($seconds);
        $ms = (int) round(($seconds - $whole) * 1000);

        if ($ms === 1000) {
            $ms = 0;
            $whole++;
        }

        $h = intdiv($whole, 3600);
        $m = intdiv($whole % 3600, 60);
        $s = $whole % 60;

        return sprintf('%02d:%02d:%02d.%03d', $h, $m, $s, $ms);
    }

    /**
     * Recorta e desloca a transcrição para a janela [cutStart, cutEnd].
     *
     * Mantém os segmentos que se sobrepõem à janela (end > cutStart &&
     * start < cutEnd), subtrai cutStart a todos os tempos (segmento e cada
     * palavra) e limita o mínimo a 0.
     *
     * @param  array<int,array<string,mixed>>  $subtitleData  Segmentos {start,end,text,words:[{word,start,end}]} em segundos.
     * @param  int|float|string  $cutStart  Início do corte (formato aceite por parseTimeToSeconds).
     * @param  int|float|string  $cutEnd  Fim do corte.
     * @return array<int,array<string,mixed>> Segmentos deslocados para começar em 0.
     */
    public static function shift(array $subtitleData, int|float|string $cutStart, int|float|string $cutEnd): array
    {
        $start = self::parseTimeToSeconds($cutStart);
        $end = self::parseTimeToSeconds($cutEnd);

        $out = [];

        foreach ($subtitleData as $seg) {
            $segStart = self::parseTimeToSeconds($seg['start'] ?? 0);
            $segEnd = self::parseTimeToSeconds($seg['end'] ?? 0);

            // Só mantém segmentos que se sobrepõem à janela do clip.
            if (! ($segEnd > $start && $segStart < $end)) {
                continue;
            }

            $words = [];
            foreach (($seg['words'] ?? []) as $word) {
                $words[] = [
                    'word' => $word['word'] ?? '',
                    'start' => max(0.0, self::parseTimeToSeconds($word['start'] ?? 0) - $start),
                    'end' => max(0.0, self::parseTimeToSeconds($word['end'] ?? 0) - $start),
                ];
            }

            $out[] = [
                'start' => max(0.0, $segStart - $start),
                'end' => max(0.0, $segEnd - $start),
                'text' => trim((string) ($seg['text'] ?? '')),
                'words' => $words,
            ];
        }

        return $out;
    }

    /**
     * Garante coerência entre o texto (possivelmente editado pelo utilizador)
     * e a lista de palavras usada pelo modo karaoke.
     *
     * Se o texto atual corresponder às palavras existentes, mantém os tempos
     * originais (precisos, do Whisper). Se o texto foi editado, redistribui as
     * novas palavras uniformemente pela janela [start, end] para que o modo
     * karaoke continue a funcionar.
     *
     * @param  array<int,array<string,mixed>>  $existingWords
     * @return array<int,array<string,mixed>>
     */
    public static function alignWords(string $text, float $start, float $end, array $existingWords): array
    {
        $text = trim($text);

        $joined = trim(implode(' ', array_map(
            fn ($w) => trim((string) ($w['word'] ?? '')),
            $existingWords
        )));

        $normalize = fn (string $s) => preg_replace('/\s+/', ' ', trim($s));

        if ($existingWords && $normalize($joined) === $normalize($text)) {
            return $existingWords;
        }

        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($tokens);

        if ($n === 0) {
            return [];
        }

        $span = max(0.0, $end - $start);
        $step = $span > 0 ? $span / $n : 0.0;

        $words = [];
        foreach ($tokens as $i => $tok) {
            $words[] = [
                'word' => $tok,
                'start' => round($start + $i * $step, 3),
                'end' => round($start + ($i + 1) * $step, 3),
            ];
        }

        return $words;
    }
}
