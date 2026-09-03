<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Speak the clip's current transcript again and re-render — without replanning.
 *
 * The edit screen lets the author rewrite a scene's subtitles; those words are
 * what the voice should say, so the new voiceover is synthesized from the
 * transcript text, not from the original script. The fresh mp3 is transcribed
 * again so karaoke gets REAL word timings for what was actually spoken (one
 * extra STT call, versus captions that drift off the audio).
 *
 * Scenes are kept as the author left them and their boundaries scaled by the
 * duration ratio, so the plan still covers the whole audio.
 */
class RegenerateVoiceoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(VoiceoverService $tts, TranscriptionService $stt, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_TRANSCRIBING, 'error' => null]);

            $text = trim((string) ($p->transcript['text'] ?? ''));
            if ($text === '') {
                throw new \RuntimeException('The clip has no transcript to speak.');
            }

            $dir = $store->storageDir($p->id);
            @mkdir($dir, 0777, true);

            $before = (float) ($p->plan['duration'] ?? 0.0);
            $audio = $tts->synthesize($text, "$dir/voz.mp3");
            $transcript = $stt->transcribe($audio);
            $after = (float) ($transcript['duration'] ?? 0.0);

            $plan = $p->plan ?? [];
            if ($before > 0.0 && $after > 0.0) {
                $plan = self::retime($plan, $after / $before);
            }
            if ($after > 0.0) {
                $plan['duration'] = $after;
            }

            $p->update([
                'audio_path' => $audio,
                'transcript' => $transcript,
                // Keep the script the voice was made from in sync with what it says,
                // so a later re-transcribe doesn't resurrect the pre-edit wording.
                'source_text' => $text,
                'plan' => $plan,
                'status' => ClipRecord::STATUS_RENDERING,
            ]);

            RenderJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Scale every scene boundary by $factor so the edited scenes still span the
     * new audio.
     *
     * ponytail: uniform scaling — a voice that stretches one sentence more than
     * another drifts within a scene. Re-align per scene from the new word
     * timings if that ever shows.
     *
     * @param  array<string,mixed>  $plan
     * @return array<string,mixed>
     */
    private static function retime(array $plan, float $factor): array
    {
        $plan['scenes'] = array_map(function (array $s) use ($factor): array {
            $s['start'] = round(((float) ($s['start'] ?? 0)) * $factor, 3);
            $s['end'] = round(((float) ($s['end'] ?? 0)) * $factor, 3);

            return $s;
        }, $plan['scenes'] ?? []);

        return $plan;
    }
}
