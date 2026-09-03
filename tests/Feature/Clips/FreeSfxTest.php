<?php

namespace Tests\Feature\Clips;

use App\Jobs\Clips\GenerateFreeSfxJob;
use App\Jobs\Clips\RenderJob;
use App\Livewire\ClipsAnimados;
use App\Services\Clips\EffectGenerator;
use App\Services\Clips\FreeSfxGenerator;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "Free SFX": the model writes a bespoke Remotion component per scene instead of
 * the clip using the stock animation vocabulary.
 *
 * The invariant every test here defends is the same one: a component that cannot
 * be trusted is DROPPED and its scene keeps the layer the planner gave it, so the
 * worst case is an ordinary clip — never a broken render.
 */
class FreeSfxTest extends TestCase
{
    use RefreshDatabase;

    /** A component that obeys the design system (tokens only, no hardcoded hue). */
    private const GOOD_TSX = <<<'TSX'
import React from "react";
import { AbsoluteFill, useCurrentFrame, interpolate } from "remotion";
import { COLORS, FONTS } from "../style-tokens";
import type { PrimitiveProps } from "../primitives";
const Bespoke: React.FC<PrimitiveProps> = ({ anim }) => {
  const frame = useCurrentFrame();
  const o = interpolate(frame, [0, 12], [0, 1], { extrapolateRight: "clamp" });
  return <AbsoluteFill style={{ opacity: o, color: COLORS.textOnLight, fontFamily: FONTS.display }}>{anim.text}</AbsoluteFill>;
};
export default Bespoke;
TSX;

    private function store(): ClipStore
    {
        return app(ClipStore::class);
    }

    private function effects(): EffectStore
    {
        return app(EffectStore::class);
    }

    /**
     * Bind a FreeSfxGenerator whose model output is fixed. It goes through the
     * REAL accept()/guard() path, so a component with a hardcoded colour is
     * rejected here exactly as it would be in production.
     *
     * @param  array<int,array<string,mixed>>  $components  raw model items
     */
    private function fakeGenerator(array $components): void
    {
        $this->app->instance(FreeSfxGenerator::class, new class(app(EffectGenerator::class), $components) extends FreeSfxGenerator
        {
            public function __construct(EffectGenerator $effects, private array $components)
            {
                parent::__construct($effects);
            }

            public function generate(array $scenes, string $subject, array $canvas = [], array $facts = []): array
            {
                $valid = array_column($scenes, 'index');
                $out = [];
                foreach ($this->components as $item) {
                    // accept() is private; reach the same result through the real
                    // guard so rejection behaviour is not faked away.
                    $one = (function () use ($item, $valid) {
                        $m = new \ReflectionMethod(FreeSfxGenerator::class, 'accept');

                        return $m->invoke($this, $item, $valid);
                    })();
                    if ($one !== null) {
                        $out[] = $one;
                    }
                }

                return $out;
            }
        });
    }

    /** @param array<int,array<string,mixed>> $scenes */
    private function clipe(array $scenes, array $meta = []): ClipRecord
    {
        return $this->store()->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'transcript' => ['duration' => 6.0, 'text' => 'A história da imprensa', 'words' => [], 'segments' => []],
            'plan' => ['duration' => 6.0, 'width' => 1080, 'height' => 1920, 'fps' => 30, 'mode' => 'dense', 'scenes' => $scenes],
            'meta' => array_merge(['free_sfx' => true], $meta),
        ]);
    }

    /** @return array<string,mixed> */
    private function cena(float $start, float $end, array $layer): array
    {
        return ['start' => $start, 'end' => $end, 'transitionIn' => 'cut', 'layers' => [$layer]];
    }

    public function test_each_eligible_scene_gets_a_bespoke_component(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Ledger slam', 'description' => 'Slams the figure down.', 'paramSchema' => '{}', 'params' => ['value' => '60%'], 'tsx' => self::GOOD_TSX],
            ['scene' => 1, 'displayName' => 'Orbit stat', 'description' => 'Orbits the stat.', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([
            $this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []]),
            $this->cena(3, 6, ['type' => 'bullet-list', 'text' => 'Second point', 'params' => []]),
        ]);

        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);

        $tipos = array_map(fn (array $s) => $s['layers'][0]['type'], $p->refresh()->plan['scenes']);
        $this->assertCount(2, array_unique($tipos), 'each scene gets its own component');
        foreach ($tipos as $slug) {
            $this->assertStringStartsWith('fx-', $slug);
        }
        $this->assertSame(['value' => '60%'], $p->plan['scenes'][0]['layers'][0]['params']);
        Queue::assertPushed(RenderJob::class);
    }

    /** Clip-scoped: registered for the renderer, absent from the SFX library. */
    public function test_the_components_are_clip_scoped_not_library_effects(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Ledger slam', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([$this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []])]);
        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);

        $this->assertCount(1, $this->effects()->forClip($p->id), 'stored against the clip');
        $this->assertCount(0, $this->effects()->all(), 'never in the SFX library');
        $this->assertCount(1, $this->effects()->active(), 'but registered, so the renderer can use it');
        $this->assertCount(0, $this->effects()->enabled(), 'and hidden from the planner');

        $file = config('contentmachine.clips.remotion_path').'/src/effects/'.$this->effects()->forClip($p->id)->first()->slug.'.tsx';
        $this->assertFileExists($file);
    }

    /** A component that breaks the design system is dropped; its scene keeps what the planner chose. */
    public function test_a_rejected_component_leaves_its_scene_untouched(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Bad', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => str_replace('COLORS.textOnLight', '"#ff8800"', self::GOOD_TSX)],
            ['scene' => 1, 'displayName' => 'Good', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([
            $this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []]),
            $this->cena(3, 6, ['type' => 'bullet-list', 'text' => 'Second point', 'params' => []]),
        ]);

        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);

        $scenes = $p->refresh()->plan['scenes'];
        $this->assertSame('card', $scenes[0]['layers'][0]['type'], 'kept the planner\'s layer');
        $this->assertStringStartsWith('fx-', $scenes[1]['layers'][0]['type']);
        $this->assertCount(1, $this->effects()->forClip($p->id));
        Queue::assertPushed(RenderJob::class);
    }

    /** The clip still renders when the model returns nothing usable at all. */
    public function test_a_failed_generation_still_renders_the_clip(): void
    {
        Queue::fake();
        $this->fakeGenerator([]);

        $p = $this->clipe([$this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []])]);
        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);

        $this->assertSame('card', $p->refresh()->plan['scenes'][0]['layers'][0]['type']);
        Queue::assertPushed(RenderJob::class);
    }

    /** An image scene keeps its image-reveal — the picture IS the visual. */
    public function test_scenes_showing_media_are_left_alone(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Ledger slam', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
            ['scene' => 1, 'displayName' => 'Orbit', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([
            $this->cena(0, 3, ['type' => 'image-reveal', 'text' => 'A laptop', 'params' => ['src' => 'img-1']]),
            $this->cena(3, 6, ['type' => 'card', 'text' => 'Second point', 'params' => []]),
        ]);

        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);

        $scenes = $p->refresh()->plan['scenes'];
        $this->assertSame('image-reveal', $scenes[0]['layers'][0]['type']);
        $this->assertSame(['src' => 'img-1'], $scenes[0]['layers'][0]['params']);
        $this->assertStringStartsWith('fx-', $scenes[1]['layers'][0]['type']);
    }

    /** Re-running (a re-plan, a retry) replaces the old components instead of piling them up. */
    public function test_rerunning_replaces_the_previous_components(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Ledger slam', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([$this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []])]);
        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);
        $primeiro = $this->effects()->forClip($p->id)->first()->id();

        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);

        $agora = $this->effects()->forClip($p->id);
        $this->assertCount(1, $agora);
        $this->assertNotSame($primeiro, $agora->first()->id());
        $this->assertCount(1, glob(config('contentmachine.clips.remotion_path').'/src/effects/fx-*.tsx') ?: []);
    }

    /** "Add to SFX library" turns a clip's one-off into an ordinary reusable effect. */
    public function test_a_component_can_be_kept_in_the_sfx_library(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Ledger slam', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([$this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []])]);
        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);
        $slug = $this->effects()->forClip($p->id)->first()->slug;

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            // The button only renders for a scene whose animation IS one of the
            // clip's own components — so the editor has to recognise the slug.
            ->assertSet('editScenes.0.animacao', $slug)
            ->call('guardarFreeSfx', $slug);

        $this->assertCount(0, $this->effects()->forClip($p->id), 'no longer tied to the clip');
        $this->assertCount(1, $this->effects()->all(), 'now a library effect');
        $this->assertCount(1, $this->effects()->enabled(), 'and offered to the planner');
    }

    /**
     * Saving from the scene editor must not strip the bespoke slug. PlanValidator
     * runs again on save, and it filters layer types when given an allowlist —
     * guardarPlano deliberately passes none, and this is what proves it.
     */
    public function test_the_bespoke_slug_survives_a_save_from_the_scene_editor(): void
    {
        Queue::fake();
        $this->fakeGenerator([
            ['scene' => 0, 'displayName' => 'Ledger slam', 'description' => 'x', 'paramSchema' => '{}', 'params' => [], 'tsx' => self::GOOD_TSX],
        ]);

        $p = $this->clipe([$this->cena(0, 3, ['type' => 'card', 'text' => 'First point', 'params' => []])]);
        $this->app->call([new GenerateFreeSfxJob($p->id), 'handle']);
        $slug = $this->effects()->forClip($p->id)->first()->slug;

        Livewire::test(ClipsAnimados::class)
            ->call('editarClip', $p->id)
            ->call('guardarPlano')
            ->assertHasNoErrors();

        $this->assertSame($slug, $p->refresh()->plan['scenes'][0]['layers'][0]['type']);
    }

    /** The toggle is on both create forms and rides on the clip. */
    public function test_free_sfx_is_stored_from_both_create_forms(): void
    {
        Queue::fake();

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'animation')
            ->set('text', 'Olá mundo Brand Machine')
            ->set('freeSfx', true)
            ->call('submitAnimation')
            ->assertHasNoErrors();

        $this->assertTrue($this->store()->all()->firstWhere('type', 'animation')->meta['free_sfx']);

        Livewire::test(ClipsAnimados::class)
            ->call('novoClip')
            ->call('escolherTipo', 'overlay')
            ->set('video', UploadedFile::fake()->create('clip.mp4', 64, 'video/mp4'))
            ->set('freeSfx', true)
            ->call('submitOverlay')
            ->assertHasNoErrors();

        $this->assertTrue($this->store()->all()->firstWhere('type', 'overlay')->meta['free_sfx']);
    }
}
