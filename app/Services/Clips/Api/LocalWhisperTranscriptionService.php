<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\TranscriptionService;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Transcription with Whisper running LOCALLY (scripts/transcribe.py, via
 * faster-whisper) — no API key, no per-minute billing. Same script the Shorts
 * pipeline already uses; this adapter reshapes its segment/word output into the
 * clip pipeline's transcript envelope, so it is a drop-in for the OpenAI service.
 */
class LocalWhisperTranscriptionService implements TranscriptionService
{
    public function transcribe(string $audioPath): array
    {
        $script = (string) config('services.shorts.transcribe_script', base_path('scripts/transcribe.py'));
        if (! is_file($script)) {
            throw new RuntimeException("Local Whisper script not found ({$script}).");
        }

        $args = [
            (string) config('services.shorts.python', 'python3'),
            $script,
            '--input', $audioPath,
            '--model', (string) config('services.shorts.whisper_model', 'tiny'),
        ];

        // Empty language = let Whisper detect it (same default as the API path).
        if ($language = (string) config('contentmachine.clips.transcribe_language', '')) {
            array_push($args, '--language', $language);
        }

        $process = new Process($args, timeout: (int) config('contentmachine.clips.whisper_timeout', 1800));
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'no output';
            throw new RuntimeException('Local Whisper failed: '.mb_substr($err, -500));
        }

        $data = json_decode($process->getOutput(), true);
        $raw = is_array($data) ? ($data['subtitle_data'] ?? $data) : null;
        if (! is_array($raw)) {
            throw new RuntimeException('Local Whisper returned no usable JSON.');
        }

        return $this->envelope($raw);
    }

    /**
     * [{start,end,text,words:[{word,start,end}]}] → the clip transcript shape
     * ({duration,text,language,words,segments}) the planner and renderer expect.
     *
     * @param  array<int,array<string,mixed>>  $raw
     */
    private function envelope(array $raw): array
    {
        $words = [];
        $segments = [];
        $texto = [];
        $fim = 0.0;

        foreach ($raw as $seg) {
            if (! is_array($seg)) {
                continue;
            }

            $inicio = (float) ($seg['start'] ?? 0.0);
            $termino = (float) ($seg['end'] ?? $inicio);
            $frase = trim((string) ($seg['text'] ?? ''));

            $segments[] = ['start' => $inicio, 'end' => $termino, 'text' => $frase];
            if ($frase !== '') {
                $texto[] = $frase;
            }
            $fim = max($fim, $termino);

            foreach ((array) ($seg['words'] ?? []) as $w) {
                if (! is_array($w)) {
                    continue;
                }
                $words[] = [
                    'word' => (string) ($w['word'] ?? ''),
                    'start' => (float) ($w['start'] ?? $inicio),
                    'end' => (float) ($w['end'] ?? $termino),
                ];
            }
        }

        return [
            'duration' => $fim,
            'text' => implode(' ', $texto),
            'language' => (string) config('contentmachine.clips.transcribe_language', '') ?: null,
            'words' => $words,
            'segments' => $segments,
        ];
    }
}
