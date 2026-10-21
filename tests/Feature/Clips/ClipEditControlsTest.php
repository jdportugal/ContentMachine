<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\RegenerateVoiceoverJob;
use App\Jobs\Clips\RenderJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The per-block controls on the edit screen: subtitles, raw video, voiceover.
 */
class ClipEditControlsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->comSessaoIniciada();
    }

    private function store(): ClipStore
    {
        return app(ClipStore::class);
    }

    /** Two scenes, one word per second, so each block owns a known run of words. */
    private function clip(string $type = ClipRecord::TYPE_ANIMATION, string $inputKind = 'audio'): ClipRecord
    {
        $words = [];
        foreach (['um', 'dois', 'tres', 'quatro'] as $i => $w) {
            $words[] = ['word' => $w, 'start' => (float) $i, 'end' => (float) $i + 1];
        }

        return $this->store()->create([
            'type' => $type,
            'input_kind' => $inputKind,
            'status' => ClipRecord::STATUS_DONE,
            'source_text' => 'um dois tres quatro',
            'transcript' => ['text' => 'um dois tres quatro', 'duration' => 4.0, 'words' => $words],
            'meta' => ['allowed_present' => ['over', 'split']],
            'plan' => ['duration' => 4.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                ['start' => 0, 'end' => 2, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'present' => 'over', 'layers' => [['type' => 'ambient', 'text' => null, 'params' => []]]],
                ['start' => 2, 'end' => 4, 'background' => 'papyrus', 'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null, 'present' => 'over', 'layers' => [['type' => 'ambient', 'text' => null, 'params' => []]]],
            ]],
        ]);
    }

    public function test_the_edit_screen_renders_the_new_controls(): void
    {
        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $this->clip(ClipRecord::TYPE_OVERLAY, 'video')->id)
            ->assertSee('legendas ditas neste bloco')
            ->assertSee('só o vídeo — sem grafismos')
            ->assertSee('Música de fundo')          // music + volume, already there
            ->assertDontSee('Guardar e gerar de novo a narração'); // a filmed clip has no TTS to redo

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $this->clip(ClipRecord::TYPE_ANIMATION, 'text')->id)
            ->assertSee('Guardar e gerar de novo a narração')
            ->assertDontSee('só o vídeo — sem grafismos');
    }

    public function test_each_block_loads_the_subtitles_spoken_in_it(): void
    {
        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $this->clip()->id)
            ->assertSet('editScenes.0.subtitles', 'um dois')
            ->assertSet('editScenes.1.subtitles', 'tres quatro');
    }

    public function test_editing_one_block_subtitles_retimes_only_that_span(): void
    {
        Queue::fake();
        $p = $this->clip();

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editScenes.1.subtitles', 'TRES QUATRO CINCO')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('um dois TRES QUATRO CINCO', $p->transcript['text']);

        $words = $p->transcript['words'];
        $this->assertSame(['um', 'dois', 'TRES', 'QUATRO', 'CINCO'], array_column($words, 'word'));
        // The untouched first block keeps its real timings...
        $this->assertSame(0.0, (float) $words[0]['start']);
        $this->assertSame(2.0, (float) $words[1]['end']);
        // ...and the edited span is spread across the gap it used to occupy.
        $this->assertSame(2.0, (float) $words[2]['start']);
        $this->assertSame(4.0, (float) $words[4]['end']);

        Queue::assertPushed(RenderJob::class);
    }

    public function test_untouched_subtitles_leave_the_transcript_alone(): void
    {
        Queue::fake();
        $p = $this->clip();
        $before = $p->transcript;

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->set('editScenes.0.background', 'ink')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        // assertEquals, not assertSame: the vault is JSON, so 4.0 comes back as 4.
        $this->assertEquals($before, $p->refresh()->transcript);
    }

    public function test_a_block_switched_to_raw_video_survives_the_validator(): void
    {
        Queue::fake();
        // 'video' is deliberately NOT in meta.allowed_present — an explicit pick wins.
        $p = $this->clip(ClipRecord::TYPE_OVERLAY, 'video');

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('editOverlay', true)
            ->set('editScenes.0.present', 'video')
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('video', $p->plan['scenes'][0]['present']);
        $this->assertSame('over', $p->plan['scenes'][1]['present']);
    }

    public function test_regenerate_voiceover_saves_the_edits_then_queues_the_job(): void
    {
        Queue::fake();
        $p = $this->clip(ClipRecord::TYPE_ANIMATION, 'text');

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->assertSet('editSpeakable', true)
            ->set('editScenes.0.subtitles', 'UM DOIS')
            ->call('guardarPlanoComVoz')
            ->assertHasNoErrors();

        $p->refresh();
        $this->assertSame('UM DOIS tres quatro', $p->transcript['text']);
        $this->assertSame(ClipRecord::STATUS_TRANSCRIBING, $p->status);
        Queue::assertPushed(RegenerateVoiceoverJob::class);
        Queue::assertNotPushed(RenderJob::class);
    }

    public function test_the_voiceover_job_speaks_the_transcript_and_scales_the_scenes(): void
    {
        Queue::fake();
        $p = $this->clip(ClipRecord::TYPE_ANIMATION, 'text');
        $p->update(['transcript' => array_merge($p->transcript, ['text' => 'um dois tres quatro cinco seis'])]);

        // Voice writes a file; the re-transcribe reports the new, longer duration.
        $this->app->bind(VoiceoverService::class, fn () => new class implements VoiceoverService
        {
            public function synthesize(string $text, string $outPath): string
            {
                @mkdir(dirname($outPath), 0777, true);
                file_put_contents($outPath, 'mp3');

                return $outPath;
            }
        });
        $this->app->bind(TranscriptionService::class, fn () => new class implements TranscriptionService
        {
            public function transcribe(string $audioPath): array
            {
                return ['text' => 'um dois tres quatro cinco seis', 'duration' => 6.0, 'words' => []];
            }
        });

        app(RegenerateVoiceoverJob::class, ['projectId' => $p->id])->handle(
            app(VoiceoverService::class),
            app(TranscriptionService::class),
            $this->store(),
        );

        $p->refresh();
        $this->assertSame(6.0, (float) $p->plan['duration']);
        // 4s of scenes stretched onto 6s of audio: every boundary × 1.5.
        $this->assertSame(3.0, (float) $p->plan['scenes'][0]['end']);
        $this->assertSame(6.0, (float) $p->plan['scenes'][1]['end']);
        // Scenes are kept, not replanned.
        $this->assertCount(2, $p->plan['scenes']);
        $this->assertSame('um dois tres quatro cinco seis', $p->source_text);
        Queue::assertPushed(RenderJob::class);
    }
}
