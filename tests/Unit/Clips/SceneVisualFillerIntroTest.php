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

    /**
     * The reported bug: the planner opened with the intro effect itself, but the
     * validator had presented that scene as `split` — the intro rendered squeezed
     * into the top half over the video. The intro must ALWAYS show full-frame.
     */
    public function test_an_intro_scene_presented_as_split_is_forced_full_frame(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'present' => 'split', 'layers' => [['type' => 'seal-stamp', 'text' => 'Brand', 'params' => []]]],
            ['start' => 2, 'end' => 4, 'present' => 'split', 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp']);

        $this->assertSame('animation', $out['scenes'][0]['present'], 'the intro must not be split/video/over');
        $this->assertSame('split', $out['scenes'][1]['present'], 'only the intro scene is touched');
    }

    /** Same guarantee when the intro is INSERTED (planner did not open with one). */
    public function test_an_inserted_intro_on_an_overlay_clip_shows_full_frame(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'present' => 'video', 'layers' => []],
            ['start' => 2, 'end' => 4, 'present' => 'split', 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp']);

        $this->assertSame('seal-stamp', $out['scenes'][0]['layers'][0]['type']);
        $this->assertSame('animation', $out['scenes'][0]['present']);
    }

    /** And when the planner's intro is too long and gets capped, present is still fixed. */
    public function test_a_capped_planner_intro_is_forced_full_frame(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 8, 'present' => 'split', 'layers' => [['type' => 'seal-stamp', 'text' => 'Brand', 'params' => []]]],
            ['start' => 8, 'end' => 10, 'present' => 'split', 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp']);

        $this->assertSame('animation', $out['scenes'][0]['present']);
        $this->assertLessThan(8.0, (float) $out['scenes'][0]['end'], 'the long opening is capped to a normal scene');
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
