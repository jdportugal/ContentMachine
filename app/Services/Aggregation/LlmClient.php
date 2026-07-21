<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo para geração de texto via LLM (OpenAI ou Gemini).
 * Sem chave configurada, `disponivel()` devolve false e a app degrada para
 * heurística. Nunca lança — em falha devolve null.
 */
class LlmClient
{
    public function disponivel(): bool
    {
        return filled(config('services.openai.key')) || filled(config('services.gemini.key'));
    }

    /** Gera texto a partir de um prompt. Devolve null se não houver chave ou em falha. */
    public function texto(string $prompt): ?string
    {
        $openai = config('services.openai.key');
        $gemini = config('services.gemini.key');

        try {
            if (filled($openai)) {
                return $this->openai((string) $openai, $prompt);
            }
            if (filled($gemini)) {
                return $this->gemini((string) $gemini, $prompt);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function openai(string $chave, string $prompt): ?string
    {
        $r = Http::timeout(90)->withToken($chave)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('contentmachine.aggregation.openai_model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
            ]);

        return $r->successful() ? trim((string) $r->json('choices.0.message.content')) ?: null : null;
    }

    private function gemini(string $chave, string $prompt): ?string
    {
        $modelo = (string) config('contentmachine.aggregation.gemini_model', 'gemini-1.5-flash');
        $r = Http::timeout(90)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$chave}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        return $r->successful() ? trim((string) $r->json('candidates.0.content.parts.0.text')) ?: null : null;
    }
}
