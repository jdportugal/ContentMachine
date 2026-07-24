<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\PlanImageAugmentor;
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
