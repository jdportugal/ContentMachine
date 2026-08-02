<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\SceneVisualFiller;
use PHPUnit\Framework\TestCase;

class SceneVisualFillerIntroTest extends TestCase
{
    public function test_intro_uses_the_opening_scenes_own_image_not_the_logo(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'image-reveal', 'params' => ['src' => 'chosen', 'variant' => 'fullscreen']]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        // Opening scene shows 'chosen'; a different image ('logo') is offered as the
        // intro logo. The intro must still show the opening part's own image.
        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp'], 'logo');

        $intro = $out['scenes'][0]['layers'][0];
        $this->assertSame('seal-stamp', $intro['type']);
        $this->assertSame('chosen', $intro['params']['src']);
    }

    public function test_intro_falls_back_to_the_logo_when_opening_has_no_image(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => ['title' => 'y']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp'], 'logo');

        $this->assertSame('logo', $out['scenes'][0]['layers'][0]['params']['src']);
    }
}
