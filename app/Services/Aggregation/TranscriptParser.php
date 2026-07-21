<?php

namespace App\Services\Aggregation;

/**
 * Converte legendas WebVTT (nomeadamente as auto-legendas do YouTube, com
 * marcações de tempo por palavra e linhas repetidas em rolo) em texto corrido
 * e legível. Função pura — sem I/O, fácil de testar com fixtures.
 */
class TranscriptParser
{
    /** Converte um documento VTT em texto simples deduplicado. */
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

            // Cabeçalhos e metadados do formato.
            if ($texto === 'WEBVTT'
                || str_starts_with($texto, 'Kind:')
                || str_starts_with($texto, 'Language:')
                || str_starts_with($texto, 'NOTE')) {
                continue;
            }

            // Linhas de tempo (cue timings) e cabeçalhos numéricos de cue.
            if (str_contains($texto, '-->') || ctype_digit($texto)) {
                continue;
            }

            // Remove marcações inline (<00:00:00.120>, <c>, </c>, etc.).
            $texto = preg_replace('/<[^>]+>/', '', $texto) ?? $texto;
            // Descodifica entidades e normaliza espaços.
            $texto = trim(preg_replace('/\s+/', ' ', html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? $texto);

            if ($texto === '') {
                continue;
            }

            // As auto-legendas repetem a linha anterior antes de acrescentar a
            // seguinte — guardamos apenas quando difere da última mantida.
            if ($texto === $ultima) {
                continue;
            }

            $saida[] = $texto;
            $ultima = $texto;
        }

        return trim(implode("\n", $saida));
    }
}
