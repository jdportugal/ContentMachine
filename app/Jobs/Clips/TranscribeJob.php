<?php

namespace App\Jobs\Clips;

use App\Models\ClipProject;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VideoCompositor;
use App\Services\Clips\Contracts\VoiceoverService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

class TranscribeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $projectId) {}

    public function handle(TranscriptionService $stt, VoiceoverService $tts, VideoCompositor $ff): void
    {
        $p = ClipProject::findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipProject::STATUS_TRANSCRIBING]);

            $dir = storage_path("app/clips/{$p->id}");
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
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
