<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\FillChosenVisualsJob;
use App\Jobs\Clips\RenderJob;
use App\Services\Clips\Api\SceneTextVisuals;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\PlanValidator;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * A scene presenting as `animation` covers the video with its graphic. With no
 * graphic there is nothing to cover it WITH, so the beat renders as bare backdrop
 * — the faint em-dash and a punch word — while the video sits hidden behind it.
 * Such a scene must fall back to showing the video.
 */
class BareSceneVideoTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<int,array<string,mixed>> $layers */
    private function cena(array $layers, ?string $present): array
    {
        return [
            'start' => 0, 'end' => 3, 'background' => 'papyrus',
            'transitionIn' => 'cut', 'karaoke' => true, 'punchWord' => null,
            'present' => $present, 'layers' => $layers,
        ];
    }

    private function validar(array $scenes, ?array $allowed = null): array
    {
        return app(PlanValidator::class)->validate(
            ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => $scenes],
            '',
            true, // overlay
            $allowed,
            app(EffectLibrary::class)->allowedLayerTypes(),
        )['scenes'];
    }

    public function test_a_layerless_animation_scene_shows_the_video_instead(): void
    {
        // The planner's own choice, and 'animation' IS allowed — but it gave the
        // scene nothing to draw, so honouring it hides the video behind nothing.
        $out = $this->validar([$this->cena([], 'animation')], ['video', 'over', 'split', 'animation']);

        $this->assertSame('video', $out[0]['present']);
    }

    public function test_an_ambient_only_animation_scene_shows_the_video_too(): void
    {
        $out = $this->validar(
            [$this->cena([['type' => 'ambient', 'text' => null, 'params' => []]], 'animation')],
            ['video', 'over', 'split', 'animation'],
        );

        $this->assertSame('video', $out[0]['present']);
    }

    public function test_a_layerless_over_or_split_scene_is_left_alone(): void
    {
        // Both keep the video on screen, so an empty one is not broken.
        foreach (['over', 'split'] as $modo) {
            $out = $this->validar([$this->cena([], $modo)], ['video', 'over', 'split', 'animation']);
            $this->assertSame($modo, $out[0]['present'], "$modo must survive");
        }
    }

    public function test_an_animation_scene_that_has_a_graphic_keeps_it(): void
    {
        $out = $this->validar(
            [$this->cena([['type' => 'card', 'text' => null, 'params' => ['title' => 'Real content']]], 'animation')],
            ['video', 'over', 'split', 'animation'],
        );

        $this->assertSame('animation', $out[0]['present']);
    }

    public function test_without_video_allowed_it_falls_back_within_the_chosen_styles(): void
    {
        // The author disallowed video-only beats — honour that rather than force it.
        $out = $this->validar([$this->cena([], 'animation')], ['over', 'split']);

        $this->assertContains($out[0]['present'], ['over', 'split']);
    }

    /**
     * The path the plan editor uses: pick a data animation, the model writes no
     * data for it, dropDeadLayers removes the layer — and this job went straight
     * to the renderer with the scene still marked `animation`.
     */
    public function test_a_dropped_visual_leaves_the_scene_showing_the_video(): void
    {
        Queue::fake();

        $p = app(ClipStore::class)->create([
            'type' => ClipRecord::TYPE_OVERLAY,
            'input_kind' => 'video',
            'source_path' => 'clips/uploads/x.mp4',
            'transcript' => ['text' => 'hello there', 'duration' => 3.0, 'words' => []],
            'meta' => ['allowed_present' => ['video', 'over', 'split', 'animation']],
            // A bar-chart with no bars: exactly what draws the em-dash.
            'plan' => ['duration' => 3.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'scenes' => [
                $this->cena([['type' => 'bar-chart', 'text' => null, 'params' => []]], 'animation'),
            ]],
        ]);

        (new FillChosenVisualsJob($p->id, [0 => 'bar-chart']))->handle(
            app(ClipStore::class),
            app(SceneTextVisuals::class),
            app(SceneVisualFiller::class),
            app(EffectLibrary::class),
        );

        $p->refresh();
        $this->assertSame([], $p->plan['scenes'][0]['layers'], 'the empty chart must be dropped');
        $this->assertSame('video', $p->plan['scenes'][0]['present'], 'and the beat must show the video');
        Queue::assertPushed(RenderJob::class);
    }
}
