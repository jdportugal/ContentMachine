<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\RealEstate\ListingScriptWriter;
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

    /**
     * @param  ?string  $text  what to say instead of the transcript (e.g. the listing's script)
     * @param  bool  $replan  plan the clip anew on the new voice (an edited transcript), not just retime it
     */
    public function __construct(public string $projectId, public ?string $text = null, public bool $replan = false)
    {
        $this->captureProject();
    }

    public function handle(VoiceoverService $tts, TranscriptionService $stt, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_TRANSCRIBING, 'error' => null]);

            // Spoken "IAD Portugal", never "iad" (voiced "IA de Portugal").
            $text = ListingScriptWriter::iadFalado(trim((string) ($this->text ?? $p->transcript['text'] ?? '')));
            if ($text === '') {
                throw new \RuntimeException('The clip has no transcript to speak.');
            }

            $dir = $store->storageDir($p->id);
            @mkdir($dir, 0777, true);

            $before = (float) ($p->plan['duration'] ?? 0.0);
            // A listing clip keeps its narrator (picked by the agent's gender), not the default voice.
            $voice = isset($p->meta['listing']['gender']) ? config('contentmachine.real_estate.voices.'.$p->meta['listing']['gender']) : null;
            $audio = $voice
                ? app(ElevenLabsVoiceoverService::class)->synthesizeWith($text, "$dir/voz.mp3", $voice['voice_id'], $voice['model'], $voice['stability'] ?? null)
                : $tts->synthesize($text, "$dir/voz.mp3");
            $transcript = $stt->transcribe($audio);
            $after = (float) ($transcript['duration'] ?? 0.0);

            // An after-intro gap is taken out, the voice retimed, and the gap opened again at the new pause.
            $filler = app(SceneVisualFiller::class);
            $waited = (float) ($p->plan['voice_gap']['seconds'] ?? 0);
            [$plan, $afterIntro, $videoLength] = $filler->removeAfterIntro($p->plan ?? []);
            $before -= $waited;
            if ($before > 0.0 && $after > 0.0) {
                $plan = self::retime($plan, $after / $before);
            }
            if ($after > 0.0) {
                $plan['duration'] = $after;
            }
            if ($afterIntro !== null) {
                $effects = app(EffectStore::class);
                $plan = $filler->insertAfterIntro($plan, ($p->meta['after_intro'] ?? null) ?: $effects->afterIntroPath() ?: $afterIntro, $videoLength, $transcript['words'] ?? [], $effects->afterIntroLineFor($p->meta ?? []));
            }
            // Clips follow the new timing of the words, and none outlasts its scene (no freeze).
            $p->attributes['transcript'] = $transcript;   // the match reads the new words (saved below)
            $plan = FinalizeClipPlanJob::showWhatIsSaid($p, $plan, $filler);

            $p->update([
                'audio_path' => $audio,
                'transcript' => $transcript,
                // Keep the script the voice was made from in sync with what it says,
                // so a later re-transcribe doesn't resurrect the pre-edit wording.
                'source_text' => $text,
                'plan' => $plan,
                'status' => $this->replan ? ClipRecord::STATUS_PLANNING : ClipRecord::STATUS_RENDERING,
            ]);
            // A listing's script follows what is now said (the "✎ Script" panel, a later re-voice).
            if (isset($p->meta['listing'])) {
                $p->update(['meta' => array_merge($p->meta, ['listing' => array_merge($p->meta['listing'], ['voiceover' => $text])])]);
            }

            $this->replan ? PlanAnimationsJob::dispatch($p->id) : RenderJob::dispatch($p->id);
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
