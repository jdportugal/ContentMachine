<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Settings\StepKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsVoiceoverService implements VoiceoverService
{
    public function synthesize(string $text, string $outPath): string
    {
        $key = StepKey::key('clips_voz', 'elevenlabs');
        if (! $key) {
            throw new RuntimeException('ELEVENLABS_API_KEY missing for voiceover.');
        }

        $voiceId = config('contentmachine.clips.voice_id');

        $bytes = Http::withHeaders([
            'xi-api-key' => $key,
            'accept' => 'audio/mpeg',
        ])
            ->timeout(300)
            ->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => 'eleven_multilingual_v2',
            ])
            ->throw()
            ->body();

        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, $bytes);

        return $outPath;
    }
}
