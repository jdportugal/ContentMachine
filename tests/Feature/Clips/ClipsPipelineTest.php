<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\TranscribeJob;
use App\Models\ClipProject;
use App\Services\Clips\Contracts\TranscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClipsPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('contentmachine.clips.driver', 'fake');
    }

    public function test_animation_pipeline_reaches_done_with_fakes(): void
    {
        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION,
            'input_kind' => 'text',
            'source_text' => 'Olá mundo IATECA',
        ]);

        TranscribeJob::dispatch($p->id); // sync queue in tests → runs the whole chain
        $p->refresh();

        $this->assertSame(ClipProject::STATUS_DONE, $p->status);
        $this->assertNotEmpty($p->plan['scenes']);
        $this->assertNotNull($p->output_path);
    }

    public function test_resolves_uploaded_audio_via_the_storage_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('clips/uploads/gravacao.webm', 'RECORDING-BYTES');

        // Transcription that fails unless the audio file is found at the resolved path —
        // guards against the storage-root mismatch (storage/app vs storage/app/private).
        $this->app->instance(TranscriptionService::class, new class implements TranscriptionService
        {
            public function transcribe(string $audioPath): array
            {
                if (! is_file($audioPath)) {
                    throw new \RuntimeException("audio not found at {$audioPath}");
                }

                return ['duration' => 2.0, 'text' => 'x', 'words' => [['word' => 'x', 'start' => 0.0, 'end' => 2.0]], 'segments' => []];
            }
        });

        $p = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'source_path' => 'clips/uploads/gravacao.webm',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipProject::STATUS_DONE, $p->status);
    }

    public function test_overlay_pipeline_reaches_done_with_fakes(): void
    {
        $p = ClipProject::create([
            'type' => ClipProject::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/example.mp4',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipProject::STATUS_DONE, $p->status);
        $this->assertNotNull($p->output_path);
    }

    public function test_overlay_plan_carries_per_scene_present_modes(): void
    {
        $p = ClipProject::create([
            'type' => ClipProject::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/example.mp4',
        ]);

        TranscribeJob::dispatch($p->id);
        $p->refresh();

        $this->assertSame(ClipProject::STATUS_DONE, $p->status);
        // the planner assigns a presentation mode per scene (video / over / split / animation)
        $presents = array_column($p->plan['scenes'], 'present');
        $this->assertContains('video', $presents);
        $this->assertContains('over', $presents);
    }
}
