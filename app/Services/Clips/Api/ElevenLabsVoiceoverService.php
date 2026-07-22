<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\VoiceoverService;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsVoiceoverService implements VoiceoverService
{
    public function synthesize(string $text, string $outPath): string
    {
        $key = config('services.elevenlabs.key');
        if (! $key) {
            throw new RuntimeException('ELEVENLABS_API_KEY em falta para locução.');
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
