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
                        'content' => json_encode(['animations' => [
                            ['start' => 0, 'end' => 1, 'primitive' => 'kinetic-text', 'text' => 'Olá', 'params' => []],
                            ['start' => 1, 'end' => 2, 'primitive' => 'not-a-real-one', 'text' => 'x'],
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
        // invalid primitive dropped, valid one kept
        $this->assertCount(1, $plan['animations']);
        $this->assertSame('kinetic-text', $plan['animations'][0]['primitive']);
    }
}
