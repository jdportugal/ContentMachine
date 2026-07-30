<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VideoCompositor;
use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class TranscribeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(TranscriptionService $stt, VoiceoverService $tts, VideoCompositor $ff, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_TRANSCRIBING]);

            $dir = $store->storageDir($p->id);
            @mkdir($dir, 0777, true);

            $disk = Storage::disk(config('contentmachine.clips.disk'));

            if ($p->input_kind === 'text') {
                $audio = $tts->synthesize($p->source_text, "$dir/voz.mp3");
            } elseif ($p->input_kind === 'video') {
                $audio = $ff->extractAudio($disk->path($p->source_path), "$dir/audio.m4a");
            } else {
                $audio = $disk->path($p->source_path);
            }

            $p->update([
                'audio_path' => $audio,
                'transcript' => $stt->transcribe($audio),
            ]);

            PlanAnimationsJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
