<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\PlanImageAugmentor;
use App\Services\Clips\SceneVisualFiller;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ClipImagesTest extends TestCase
{
    /** Install a fake image generator (no real kie.ai calls). */
    private function fakeGenerator(bool $configured = true): void
    {
        $this->app->instance(ClipImageGenerator::class, new class($configured) extends ClipImageGenerator
        {
            public function __construct(private bool $on) {}

            public function configured(): bool
            {
                return $this->on;
            }

            public function generate(string $prompt, string $style = ''): ?array
            {
                return [
                    'id' => 'gen_'.substr(md5($prompt), 0, 8),
                    'path' => 'clips/generated/'.md5($prompt).'.png',
                    'description' => $prompt,
                    'tone' => 'mixed',
                    'transparent' => false,
                    'generated' => true,
                ];
            }
        });
    }

    public function test_augmentor_generates_images_for_generate_directives(): void
    {
        $this->fakeGenerator();

        $plan = ['scenes' => [
            ['layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'a red fox in snow', 'variant' => 'fullscreen']]]],
            ['layers' => [['type' => 'image-reveal', 'params' => ['src' => 'img_existing']]]], // provided → untouched
            ['layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        $result = app(PlanImageAugmentor::class)->augment($plan, [], 'cinematic', 6);

        $p0 = $result['plan']['scenes'][0]['layers'][0]['params'];
        $this->assertArrayNotHasKey('generate', $p0);       // directive stripped
        $this->assertStringStartsWith('gen_', $p0['src']);  // replaced by a generated id
        $this->assertCount(1, $result['images']);           // appended to clip images
        $this->assertSame($p0['src'], $result['images'][0]['id']);

        // A provided image is left alone.
        $this->assertSame('img_existing', $result['plan']['scenes'][1]['layers'][0]['params']['src']);
    }

    public function test_augmentor_caps_the_number_of_generated_images(): void
    {
        $this->fakeGenerator();

        $plan = ['scenes' => array_map(fn ($i) => [
            'layers' => [['type' => 'image-reveal', 'params' => ['generate' => "image number {$i}"]]],
        ], range(1, 4))];

        $result = app(PlanImageAugmentor::class)->augment($plan, [], '', 2);

        $this->assertCount(2, $result['images']); // capped at 2
        $withSrc = 0;
        foreach ($result['plan']['scenes'] as $s) {
            $params = $s['layers'][0]['params'];
            $this->assertArrayNotHasKey('generate', $params); // always stripped
            $withSrc += isset($params['src']) ? 1 : 0;
        }
        $this->assertSame(2, $withSrc);
    }

    public function test_augmentor_strips_directives_when_generation_is_unavailable(): void
    {
        $this->fakeGenerator(configured: false);

        $plan = ['scenes' => [['layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'x']]]]]];
        $result = app(PlanImageAugmentor::class)->augment($plan, [], '', 6);

        $params = $result['plan']['scenes'][0]['layers'][0]['params'];
        $this->assertArrayNotHasKey('generate', $params);
        $this->assertArrayNotHasKey('src', $params);
        $this->assertSame([], $result['images']);
    }

    public function test_filler_gives_empty_animation_scenes_a_generate_directive(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => []],                                 // empty → generate
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => []]]], // has visual → untouched
            ['start' => 4, 'end' => 6, 'layers' => [['type' => 'ambient', 'params' => []]]], // ambient only → generate
        ]];
        $transcript = ['words' => [
            ['word' => 'hello', 'start' => 0.1], ['word' => 'world', 'start' => 0.5],
            ['word' => 'foxes', 'start' => 4.2], ['word' => 'run', 'start' => 4.8],
        ]];

        $out = $filler->requestImages($plan, $transcript);

        $l0 = end($out['scenes'][0]['layers']);
        $this->assertSame('image-reveal', $l0['type']);
        $this->assertStringContainsString('hello world', $l0['params']['generate']);

        $this->assertCount(1, $out['scenes'][1]['layers']); // card scene untouched
        $this->assertSame('card', $out['scenes'][1]['layers'][0]['type']);

        $l2 = end($out['scenes'][2]['layers']);             // ambient-only scene now has a visual
        $this->assertSame('image-reveal', $l2['type']);
        $this->assertStringContainsString('foxes run', $l2['params']['generate']);
    }

    public function test_enforce_intro_is_a_no_op_without_intro_effects(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [['start' => 0, 'end' => 2, 'layers' => [['type' => 'card', 'params' => []]]]]];

        $this->assertSame($plan, $filler->enforceIntro($plan, []));
    }

    public function test_enforce_intro_respects_a_first_scene_that_already_opens_with_an_intro(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'logo-burst', 'text' => 'Hi', 'params' => []]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => []]]],
        ]];

        $this->assertSame($plan, $filler->enforceIntro($plan, ['logo-burst', 'seal-stamp']));
    }

    public function test_enforce_intro_forces_the_opening_scene_to_the_intro_effect(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'punchWord' => 'BOOM', 'layers' => [['type' => 'bar-chart', 'text' => 'Sales', 'params' => ['bars' => []]]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => []]]],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-burst']);

        $this->assertSame([['type' => 'logo-burst', 'text' => 'BOOM', 'params' => []]], $out['scenes'][0]['layers']);
        $this->assertNull($out['scenes'][0]['punchWord']);
        $this->assertSame('card', $out['scenes'][1]['layers'][0]['type']); // rest untouched
    }

    public function test_enforce_intro_shows_full_screen_on_an_overlay_scene(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'present' => 'video', 'layers' => []],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-burst']);

        $this->assertSame('animation', $out['scenes'][0]['present']);
        $this->assertSame('logo-burst', $out['scenes'][0]['layers'][0]['type']);
    }

    public function test_enforce_intro_caps_a_long_opening_and_keeps_the_original_content(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 6, 'layers' => [['type' => 'bar-chart', 'text' => 'Sales', 'params' => ['bars' => []]]]],
            ['start' => 6, 'end' => 8, 'layers' => [['type' => 'card', 'params' => []]]],
            ['start' => 8, 'end' => 10, 'layers' => [['type' => 'terminal', 'params' => []]]],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-burst']);

        // Intro is a short scene (median of the others = 2s), original content follows.
        $this->assertCount(4, $out['scenes']);
        $this->assertSame('logo-burst', $out['scenes'][0]['layers'][0]['type']);
        $this->assertSame(0.0, (float) $out['scenes'][0]['start']);
        $this->assertSame(2.0, (float) $out['scenes'][0]['end']);
        $this->assertSame('bar-chart', $out['scenes'][1]['layers'][0]['type']);
        $this->assertSame(2.0, (float) $out['scenes'][1]['start']);
        $this->assertSame(6.0, (float) $out['scenes'][1]['end']);
    }

    public function test_enforce_intro_caps_a_long_planner_intro_by_sliding_the_next_scene(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 6, 'layers' => [['type' => 'logo-burst', 'text' => 'Hi', 'params' => []]]],
            ['start' => 6, 'end' => 8, 'layers' => [['type' => 'card', 'params' => []]]],
            ['start' => 8, 'end' => 10, 'layers' => [['type' => 'terminal', 'params' => []]]],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-burst']);

        // No new scene; the intro is shortened and the next scene starts earlier.
        $this->assertCount(3, $out['scenes']);
        $this->assertSame(2.0, (float) $out['scenes'][0]['end']);
        $this->assertSame(2.0, (float) $out['scenes'][1]['start']);
        $this->assertSame('card', $out['scenes'][1]['layers'][0]['type']);
    }

    public function test_enforce_intro_feeds_a_provided_image_to_a_forced_image_intro(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'card', 'params' => []]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'terminal', 'params' => []]]],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-assemble'], 'img_logo');

        $this->assertSame('logo-assemble', $out['scenes'][0]['layers'][0]['type']);
        $this->assertSame('img_logo', $out['scenes'][0]['layers'][0]['params']['src']);
    }

    public function test_enforce_intro_injects_the_image_when_the_planner_forgot_the_src(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'logo-assemble', 'params' => []]]], // opened with intro, no src
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => []]]],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-assemble'], 'img_logo');

        $this->assertSame('img_logo', $out['scenes'][0]['layers'][0]['params']['src']);
    }

    public function test_enforce_intro_keeps_a_src_the_planner_already_chose(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'logo-assemble', 'params' => ['src' => 'img_chosen']]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => []]]],
        ]];

        $out = $filler->enforceIntro($plan, ['logo-assemble'], 'img_logo');

        $this->assertSame('img_chosen', $out['scenes'][0]['layers'][0]['params']['src']); // untouched
    }

    public function test_drop_dead_layers_removes_empty_image_and_chart_layers(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['layers' => [['type' => 'image-reveal', 'params' => ['caption' => 'x']]]],              // no src → dropped
            ['layers' => [['type' => 'image-reveal', 'params' => ['src' => 'gen_abc']]]],            // has src → kept
            ['layers' => [['type' => 'bar-chart', 'params' => []]]],                                 // no data → dropped
            ['layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'A', 'value' => 1]]]]]], // kept
            ['layers' => [['type' => 'card', 'params' => ['title' => 'Hi']]]],                       // kept
            ['layers' => [['type' => 'kinetic-text', 'text' => 'yo', 'params' => []]]],              // text → kept
        ]];

        $out = $filler->dropDeadLayers($plan);

        $this->assertCount(0, $out['scenes'][0]['layers']);
        $this->assertCount(1, $out['scenes'][1]['layers']);
        $this->assertCount(0, $out['scenes'][2]['layers']);
        $this->assertCount(1, $out['scenes'][3]['layers']);
        $this->assertCount(1, $out['scenes'][4]['layers']);
        $this->assertCount(1, $out['scenes'][5]['layers']);
    }

    public function test_drop_dead_layers_removes_effectively_blank_text_layers(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['layers' => [['type' => 'card', 'params' => ['title' => '', 'lines' => ['']]]]],   // blank → dropped
            ['layers' => [['type' => 'card', 'text' => 'Titled via text', 'params' => []]]],    // title from text → kept
            ['layers' => [['type' => 'bullet-list', 'params' => ['items' => ['', '  ']]]]],      // blank items → dropped
            ['layers' => [['type' => 'terminal', 'params' => ['lines' => []]]]],                 // empty → dropped
        ]];

        $out = $filler->dropDeadLayers($plan);

        $this->assertCount(0, $out['scenes'][0]['layers']);
        $this->assertCount(1, $out['scenes'][1]['layers']);
        $this->assertCount(0, $out['scenes'][2]['layers']);
        $this->assertCount(0, $out['scenes'][3]['layers']);
    }

    public function test_fill_bare_scenes_uses_ambient_and_drops_a_lone_punchword(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['punchWord' => 'MISSISSIPPI', 'layers' => []],                      // bare + punch → ambient, no lone word
            ['layers' => [['type' => 'card', 'params' => ['title' => 'Hi']]]],   // has visual → untouched
        ]];

        $out = $filler->fillBareScenes($plan);

        $this->assertSame([['type' => 'ambient', 'text' => null, 'params' => []]], $out['scenes'][0]['layers']);
        $this->assertNull($out['scenes'][0]['punchWord']); // no scene is "just one word"
        $this->assertSame('card', $out['scenes'][1]['layers'][0]['type']);
    }

    public function test_merge_bare_scenes_absorbs_them_into_a_neighbour_visual(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 5, 'transitionOut' => 'cut', 'layers' => []],                        // leading bare → next
            ['start' => 5, 'end' => 10, 'transitionOut' => 'whip', 'layers' => [['type' => 'card', 'params' => ['title' => 'A']]]],
            ['start' => 10, 'end' => 15, 'transitionIn' => 'slide', 'transitionOut' => 'zoom', 'layers' => []], // interior bare → previous
            ['start' => 15, 'end' => 20, 'transitionOut' => 'cut', 'layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'x', 'value' => 1]]]]]],
            ['start' => 20, 'end' => 25, 'transitionOut' => 'cut', 'layers' => []],                       // trailing bare → previous
        ]];

        $out = $filler->mergeBareScenes($plan)['scenes'];

        // No bare scenes survive, coverage stays contiguous 0..25.
        $this->assertCount(2, $out);
        // card absorbs the leading (0..5) + interior (10..15) bare → 0..15;
        // bar-chart absorbs the trailing bare (20..25) → 15..25.
        $this->assertSame([0.0, 15.0, 25.0], [(float) $out[0]['start'], (float) $out[0]['end'], (float) $out[1]['end']]);
        $this->assertSame('card', $out[0]['layers'][0]['type']);
        $this->assertSame('bar-chart', $out[1]['layers'][0]['type']);
        $this->assertEmpty(array_filter($out, fn ($s) => empty($s['layers'])));
    }

    public function test_planner_prompt_toggles_image_generation_by_availability(): void
    {
        $planner = new class
        {
            use BuildsAnimationPrompt;

            public function withImages(): string
            {
                return $this->systemPrompt('dense', false, [], true);
            }

            public function withoutImages(): string
            {
                return $this->systemPrompt('dense', false, [], false);
            }
        };

        $on = $planner->withImages();
        $this->assertStringContainsString('GENERATE AN IMAGE', $on);

        $off = $planner->withoutImages();
        $this->assertStringNotContainsString('GENERATE AN IMAGE', $off);
        $this->assertStringContainsString('IMAGE GENERATION IS UNAVAILABLE', $off);
    }

    public function test_generator_availability_reflects_a_credit_failure(): void
    {
        config(['services.kie.key' => 'test-kie-key', 'cache.default' => 'array']);
        Cache::forget('clips.images_unavailable');

        $gen = app(ClipImageGenerator::class);
        $this->assertTrue($gen->available());

        // A recorded 402 makes images unavailable (so the planner plans around it).
        Cache::put('clips.images_unavailable', true, now()->addMinutes(30));
        $this->assertFalse($gen->available());
    }

    /** The src of the image-reveal layer in a scene, or null. */
    private function imageSrc(array $scene): ?string
    {
        foreach ($scene['layers'] ?? [] as $l) {
            if (($l['type'] ?? '') === 'image-reveal') {
                return $l['params']['src'] ?? null;
            }
        }

        return null;
    }

    public function test_provided_image_is_forced_into_the_lowest_value_scene(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'A', 'value' => 1]]]]]], // data → rank 2
            ['start' => 2, 'end' => 4, 'punchWord' => 'HELLO', 'layers' => [['type' => 'kinetic-text', 'text' => 'Hello', 'params' => []]]], // ornament → rank 0
        ]];

        $out = $filler->ensureProvidedImages($plan, [['id' => 'img_a', 'path' => 'x.png']], ['bar-chart', 'kinetic-text', 'image-reveal', 'ambient']);

        // Went to the ornament scene, not the chart.
        $this->assertSame('img_a', $this->imageSrc($out['scenes'][1]));
        $this->assertNull($out['scenes'][1]['punchWord']);          // image carries the frame
        $this->assertSame('bar-chart', $out['scenes'][0]['layers'][0]['type']); // chart untouched
    }

    public function test_provided_images_go_to_distinct_scenes_by_priority(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['layers' => [['type' => 'pie-chart', 'params' => ['slices' => [['label' => 'A', 'value' => 1]]]]]], // rank 2
            ['layers' => [['type' => 'ambient', 'params' => []]]],                                                 // rank 0
            ['layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'a fox']]]],                       // rank 1 (AI image)
        ]];

        $out = $filler->ensureProvidedImages($plan, [['id' => 'img_a'], ['id' => 'img_b']], ['pie-chart', 'ambient', 'image-reveal']);

        // Priority order [1 (ambient), 2 (ai-image), 0 (chart)] → a to scene1, b to scene2.
        $this->assertSame('img_a', $this->imageSrc($out['scenes'][1]));
        $this->assertSame('img_b', $this->imageSrc($out['scenes'][2]));
        $this->assertSame('pie-chart', $out['scenes'][0]['layers'][0]['type']); // real data preserved
        // Ambient kept underneath the image on scene 1.
        $this->assertSame('ambient', $out['scenes'][1]['layers'][0]['type']);
    }

    public function test_already_placed_image_is_not_duplicated(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['layers' => [['type' => 'image-reveal', 'params' => ['src' => 'img_a']]]],
            ['layers' => [['type' => 'kinetic-text', 'text' => 'x', 'params' => []]]],
        ]];

        $out = $filler->ensureProvidedImages($plan, [['id' => 'img_a']], ['image-reveal', 'kinetic-text']);

        $this->assertSame($plan['scenes'], $out['scenes']); // planner already used it — no change
    }

    public function test_no_enforcement_when_image_reveal_is_disabled(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [['layers' => [['type' => 'kinetic-text', 'text' => 'x', 'params' => []]]]]];

        // image-reveal not in the allowed set → nothing can show an image, so no-op.
        $out = $filler->ensureProvidedImages($plan, [['id' => 'img_a']], ['kinetic-text', 'ambient']);

        $this->assertSame($plan['scenes'], $out['scenes']);
    }

    public function test_text_heavy_scene_borrows_time_from_a_following_low_value_scene(): void
    {
        $filler = new SceneVisualFiller;
        $items = ['Install the CLI and authenticate', 'Configure the environment file', 'Run the first migration', 'Deploy to production'];
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 1.5, 'layers' => [['type' => 'bullet-list', 'params' => ['items' => $items]]]], // ~105 chars → needs ~6s
            ['start' => 1.5, 'end' => 8.0, 'layers' => [['type' => 'ambient', 'params' => []]]],                     // low-value → lends time
        ]];

        $out = $filler->enforceReadingTime($plan)['scenes'];

        // The bullet-list scene grew toward its reading time; coverage stays contiguous.
        $this->assertGreaterThanOrEqual(4.5, $out[0]['end'] - $out[0]['start']);
        $this->assertSame((float) $out[0]['end'], (float) $out[1]['start']); // no gap
        $this->assertSame(8.0, (float) $out[1]['end']);                      // total unchanged
    }

    public function test_reading_time_never_steals_from_a_real_visual(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 1.5, 'layers' => [['type' => 'bullet-list', 'params' => ['items' => ['Install the CLI and authenticate now', 'Configure the environment file fully']]]]],
            ['start' => 1.5, 'end' => 6.0, 'layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'A', 'value' => 1]]]]]], // real visual → protected
        ]];

        $out = $filler->enforceReadingTime($plan)['scenes'];

        // The chart keeps its full window — nothing borrowed.
        $this->assertSame(1.5, (float) $out[0]['end']);
        $this->assertSame(1.5, (float) $out[1]['start']);
    }
}
