<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Costs\CostLedger;
use App\Services\Settings\StepKey;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ElevenLabsVoiceoverService implements VoiceoverService
{
    public function synthesize(string $text, string $outPath): string
    {
        return $this->synthesizeWith($text, $outPath, (string) config('contentmachine.clips.voice_id'), 'eleven_multilingual_v2');
    }

    /** Same, with an explicit voice and model (the listing importer picks them per agent). */
    public function synthesizeWith(string $text, string $outPath, string $voiceId, string $model, ?float $stability = null): string
    {
        $text = self::paragraphs($text);
        $key = StepKey::key('clips_voz', 'elevenlabs');
        if (! $key) {
            throw new RuntimeException('ELEVENLABS_API_KEY missing for voiceover.');
        }

        app(CostLedger::class)->registar(
            'elevenlabs',
            mb_strlen($text) / 1000 * (float) config('contentmachine.custos.elevenlabs_1k_chars', 0.30),
            mb_strlen($text).' chars',
        );

        // v3 goes through the dialogue endpoint, as the ElevenLabs website does.
        [$url, $body] = str_starts_with($model, 'eleven_v3')
            ? ['https://api.elevenlabs.io/v1/text-to-dialogue', [
                'inputs' => [['text' => $text, 'voice_id' => $voiceId]],
                'model_id' => $model,
            ] + ($stability !== null ? ['settings' => ['stability' => $stability]] : [])]
            : ["https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text' => $text,
                'model_id' => $model,
            ] + ($stability !== null ? ['voice_settings' => ['stability' => $stability, 'similarity_boost' => 0.75, 'style' => 0, 'use_speaker_boost' => true]] : [])];

        $bytes = Http::withHeaders([
            'xi-api-key' => $key,
            'accept' => 'audio/mpeg',
        ])
            ->timeout(300)
            ->post($url, $body)
            ->throw()
            ->body();

        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, $bytes);

        return $outPath;
    }

    /**
     * One block of text → paragraphs of two sentences. Read as a single block, Simão
     * drifts to a Brazilian accent; split into paragraphs (as typed on the website)
     * he stays European. Text that already has line breaks is left as it is.
     */
    public static function paragraphs(string $text): string
    {
        $text = trim($text);
        if (str_contains($text, "\n")) {
            return $text;
        }
        $sentences = preg_split('/(?<=[.!?…])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

        return implode("\n\n", array_map(fn ($pair) => implode(' ', $pair), array_chunk($sentences, 2)));
    }
}
