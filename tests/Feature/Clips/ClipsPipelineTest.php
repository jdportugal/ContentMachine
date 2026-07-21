<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\TranscribeJob;
use App\Models\ClipProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->assertNotEmpty($p->plan['animations']);
        $this->assertNotNull($p->output_path);
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
}
