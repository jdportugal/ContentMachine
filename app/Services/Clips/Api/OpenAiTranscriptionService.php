<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\TranscriptionService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiTranscriptionService implements TranscriptionService
{
    public function transcribe(string $audioPath): array
    {
        $key = config('services.openai.key');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY em falta para transcrição.');
        }

        $payload = [
            'model' => 'whisper-1',
            'response_format' => 'verbose_json',
            'timestamp_granularities[]' => 'word',
        ];

        if ($language = config('contentmachine.clips.transcribe_language')) {
            $payload['language'] = $language;
        }

        $response = Http::withToken($key)
            ->timeout(300)
            ->attach('file', file_get_contents($audioPath), basename($audioPath))
            ->post('https://api.openai.com/v1/audio/transcriptions', $payload)
            ->throw()
            ->json();

        $words = array_map(fn ($w) => [
            'word' => $w['word'],
            'start' => (float) $w['start'],
            'end' => (float) $w['end'],
        ], $response['words'] ?? []);

        $segments = array_map(fn ($s) => [
            'start' => (float) $s['start'],
            'end' => (float) $s['end'],
            'text' => $s['text'],
        ], $response['segments'] ?? []);

        $duration = (float) ($response['duration']
            ?? (! empty($words) ? end($words)['end'] : 0.0));

        return [
            'duration' => $duration,
            'text' => $response['text'] ?? '',
            'language' => $response['language'] ?? null,
            'words' => $words,
            'segments' => $segments,
        ];
    }
}
