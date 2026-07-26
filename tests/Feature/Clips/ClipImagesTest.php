<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\PlanImageAugmentor;
use App\Services\Clips\SceneVisualFiller;
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

    public function test_fill_bare_scenes_adds_a_kinetic_headline_over_ambient(): void
    {
        $filler = new SceneVisualFiller;
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'punchWord' => 'MISSISSIPPI', 'layers' => []],   // bare + punch → headline
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => ['title' => 'Hi']]]], // untouched
            ['start' => 4, 'end' => 6, 'layers' => []],                                   // bare, no words → ambient only
        ]];
        $transcript = ['words' => []];

        $out = $filler->fillBareScenes($plan, $transcript);

        // Scene 0: ambient + a kinetic headline of the punch word; punchWord moved into it.
        $types0 = array_column($out['scenes'][0]['layers'], 'type');
        $this->assertContains('ambient', $types0);
        $this->assertContains('kinetic-text', $types0);
        $kinetic = collect($out['scenes'][0]['layers'])->firstWhere('type', 'kinetic-text');
        $this->assertSame('MISSISSIPPI', $kinetic['text']);
        $this->assertNull($out['scenes'][0]['punchWord']);

        // Scene 1 untouched.
        $this->assertSame('card', $out['scenes'][1]['layers'][0]['type']);

        // Scene 2: no punch, no words → ambient only.
        $this->assertSame([['type' => 'ambient', 'text' => null, 'params' => []]], $out['scenes'][2]['layers']);
    }

    public function test_planner_prompt_offers_image_generation(): void
    {
        $planner = new class
        {
            use BuildsAnimationPrompt;

            public function prompt(): string
            {
                return $this->systemPrompt('dense');
            }
        };

        $prompt = $planner->prompt();
        $this->assertStringContainsString('generate', $prompt);
        $this->assertStringContainsString('GENERATE AN IMAGE', $prompt);
    }
}
