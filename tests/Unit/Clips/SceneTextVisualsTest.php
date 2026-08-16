<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Api\SceneTextVisuals;
use App\Services\Clips\ImageRequests;
use Tests\TestCase;

/** Declining an image suggestion turns that scene into a non-image visual. */
class SceneTextVisualsTest extends TestCase
{
    private function plan(): array
    {
        return ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [
                ['type' => 'ambient', 'params' => []],
                ['type' => 'image-reveal', 'params' => ['generate' => 'a laptop on a desk']],
            ]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'image-reveal', 'params' => ['generate' => 'a busy street']]]],
        ]];
    }

    private function transcript(): array
    {
        return ['words' => [
            ['word' => 'primeiro', 'start' => 0.1],
            ['word' => 'passo', 'start' => 0.6],
            ['word' => 'depois', 'start' => 2.2],
        ]];
    }

    /** The service with a canned LLM answer (no CLI / API call). */
    private function service(string $answer): SceneTextVisuals
    {
        return new class($answer) extends SceneTextVisuals
        {
            public function __construct(private string $answer) {}

            protected function runClaude(string $user, ?string $system = null, array $opts = []): array
            {
                return ['result' => $this->answer];
            }
        };
    }

    public function test_declined_suggestion_becomes_a_text_layer_and_the_others_are_untouched(): void
    {
        config(['contentmachine.clips.driver' => 'api']);
        $keys = [ImageRequests::key('a laptop on a desk')];

        $out = $this->service('{"0": {"type": "card", "params": {"title": "Primeiro passo", "lines": ["Abre o editor"]}}}')
            ->replace($this->plan(), $keys, $this->transcript());

        // Scene 0: image layer gone, card in its place, ambient kept.
        $types = array_column($out['scenes'][0]['layers'], 'type');
        $this->assertSame(['ambient', 'card'], $types);
        $this->assertSame('Primeiro passo', $out['scenes'][0]['layers'][1]['params']['title']);

        // Scene 1 was not declined — its image request is intact.
        $this->assertSame('a busy street', $out['scenes'][1]['layers'][0]['params']['generate']);
    }

    public function test_without_a_usable_answer_the_image_layer_is_just_dropped(): void
    {
        config(['contentmachine.clips.driver' => 'api']);
        $keys = [ImageRequests::key('a laptop on a desk')];

        $out = $this->service('not json at all')->replace($this->plan(), $keys, $this->transcript());

        $this->assertSame(['ambient'], array_column($out['scenes'][0]['layers'], 'type'));
    }

    public function test_no_keys_leaves_the_plan_alone(): void
    {
        $this->assertSame($this->plan(), $this->service('{}')->replace($this->plan(), [], $this->transcript()));
    }
}
