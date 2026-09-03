<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Settings\StepKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Process\Process;

class OpenAiTranscriptionService implements TranscriptionService
{
    /** Whisper rejects uploads over 25 MB (26214400 bytes). */
    private const MAX_UPLOAD_BYTES = 26214400;

    public function transcribe(string $audioPath): array
    {
        $key = StepKey::key('clips_transcricao', 'openai');
        if (! $key) {
            throw new RuntimeException('OPENAI_API_KEY missing for transcription.');
        }

        $audioPath = $this->fitUnderUploadLimit($audioPath);

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

    /**
     * Whisper caps uploads at 25 MB; a >21-minute video's 160k AAC track already
     * overflows it. Re-encode oversized audio to speech-grade mono (16 kHz, 48 kbps
     * ≈ 70 min under the cap) — duration is unchanged, so word timestamps still line up.
     */
    private function fitUnderUploadLimit(string $audioPath): string
    {
        if (filesize($audioPath) <= self::MAX_UPLOAD_BYTES) {
            return $audioPath;
        }

        $out = dirname($audioPath).'/'.pathinfo($audioPath, PATHINFO_FILENAME).'-whisper.m4a';
        $process = new Process([
            'ffmpeg', '-y', '-i', $audioPath,
            '-vn', '-ac', '1', '-ar', '16000', '-c:a', 'aac', '-b:a', '48k',
            $out,
        ]);
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful() || ! is_file($out)) {
            throw new RuntimeException('Audio exceeds the 25 MB Whisper limit and re-encoding failed: '.trim($process->getErrorOutput()));
        }
        if (filesize($out) > self::MAX_UPLOAD_BYTES) {
            // ponytail: ~70 min of speech still overflows at 48k mono; chunked transcription if that ever happens.
            throw new RuntimeException('Audio is too long to transcribe in one Whisper upload even after compression.');
        }

        return $out;
    }
}
