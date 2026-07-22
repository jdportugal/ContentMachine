<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Api\OpenAiAnimationPlanner;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiPlannerTest extends TestCase
{
    public function test_planner_decodes_openai_json_and_filters_invalid_primitives(): void
    {
        config()->set('services.openai.key', 'sk-test');

        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['scenes' => [
                            [
                                'start' => 0, 'end' => 2, 'background' => 'papyrus', 'transitionIn' => 'cut',
                                'karaoke' => true, 'punchWord' => 'OLÁ',
                                'layers' => [
                                    ['type' => 'kinetic-text', 'text' => 'Olá', 'params' => []],
                                    ['type' => 'not-a-real-one', 'text' => 'x'],
                                ],
                            ],
                            [
                                'start' => 2, 'end' => 4, 'background' => 'vellum', 'transitionIn' => 'crossfade',
                                'layers' => [['type' => 'timeline', 'params' => [
                                    'items' => [['label' => 'Fable 3'], ['label' => 'Fable 5', 'highlight' => true]],
                                ]]],
                            ],
                        ]]),
                    ],
                ]],
            ]),
        ]);

        $transcript = [
            'duration' => 2.0,
            'words' => [['word' => 'Olá', 'start' => 0.0, 'end' => 1.0]],
        ];

        $plan = (new OpenAiAnimationPlanner)->plan($transcript, 'dense', [
            'width' => 1080, 'height' => 1920, 'fps' => 30,
        ]);

        $this->assertSame('dense', $plan['mode']);
        $this->assertSame(2.0, $plan['duration']);
        $this->assertCount(2, $plan['scenes']);
        // scene 1: karaoke + punch word, invalid layer dropped, valid kept
        $this->assertTrue($plan['scenes'][0]['karaoke']);
        $this->assertSame('OLÁ', $plan['scenes'][0]['punchWord']);
        $this->assertCount(1, $plan['scenes'][0]['layers']);
        $this->assertSame('kinetic-text', $plan['scenes'][0]['layers'][0]['type']);
        // scene 2: timeline layer with params preserved
        $this->assertSame('timeline', $plan['scenes'][1]['layers'][0]['type']);
        $this->assertTrue($plan['scenes'][1]['layers'][0]['params']['items'][1]['highlight']);
    }
}
