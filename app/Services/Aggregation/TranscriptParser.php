<?php

namespace App\Services\Aggregation;

/**
 * Converts WebVTT subtitles (namely YouTube's auto-captions, with
 * per-word timings and rolling repeated lines) into flowing,
 * readable text. Pure function — no I/O, easy to test with fixtures.
 */
class TranscriptParser
{
    /** Converts a VTT document into deduplicated plain text. */
    public function vttToText(string $vtt): string
    {
        $linhas = preg_split('/\r\n|\r|\n/', $vtt) ?: [];
        $saida = [];
        $ultima = null;

        foreach ($linhas as $linha) {
            $texto = trim($linha);

            if ($texto === '') {
                continue;
            }

            // Format headers and metadata.
            if ($texto === 'WEBVTT'
                || str_starts_with($texto, 'Kind:')
                || str_starts_with($texto, 'Language:')
                || str_starts_with($texto, 'NOTE')) {
                continue;
            }

            // Cue timing lines and numeric cue headers.
            if (str_contains($texto, '-->') || ctype_digit($texto)) {
                continue;
            }

            // Remove inline markup (<00:00:00.120>, <c>, </c>, etc.).
            $texto = preg_replace('/<[^>]+>/', '', $texto) ?? $texto;
            // Decode entities and normalize whitespace.
            $texto = trim(preg_replace('/\s+/', ' ', html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $texto);

            if ($texto === '') {
                continue;
            }

            // Auto-captions repeat the previous line before adding the
            // next — we keep only when it differs from the last kept one.
            if ($texto === $ultima) {
                continue;
            }

            $saida[] = $texto;
            $ultima = $texto;
        }

        return trim(implode("\n", $saida));
    }
}
