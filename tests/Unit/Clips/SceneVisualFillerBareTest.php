<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\SceneVisualFiller;
use PHPUnit\Framework\TestCase;

class SceneVisualFillerBareTest extends TestCase
{
    /**
     * Overlay clips composite the source video, so a `video`/`over`/`split` scene
     * with no layers is NOT bare — the video is the visual. Treating it as bare
     * merged real scenes away.
     */
    public function test_a_scene_showing_the_source_video_is_never_bare(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'present' => 'animation', 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
            ['start' => 3, 'end' => 6, 'present' => 'video', 'layers' => []],
            ['start' => 6, 'end' => 9, 'present' => 'split', 'layers' => []],
        ]];

        $out = (new SceneVisualFiller)->mergeBareScenes($plan);

        $this->assertCount(3, $out['scenes'], 'video-bearing scenes must survive');
    }

    /**
     * When the user disallowed video-only beats (allowed_present without "video"),
     * a bare overlay `animation` scene is recovered the animation-clip way instead:
     * the neighbour's visual extends over it (the reported bare outro at 0:29).
     */
    public function test_a_bare_overlay_scene_merges_into_a_neighbour_with_a_visual(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 29, 'present' => 'animation', 'layers' => [['type' => 'comparison', 'params' => ['left' => ['title' => 'x']]]]],
            ['start' => 29, 'end' => 34, 'present' => 'animation', 'punchWord' => 'transparency', 'layers' => []],
        ]];

        $out = (new SceneVisualFiller)->mergeBareScenes($plan);

        $this->assertCount(1, $out['scenes']);
        $this->assertSame(34, $out['scenes'][0]['end'], "the neighbour's visual must cover the bare beat");
    }

    /**
     * An overlay `animation` scene with no layers rendered as nothing but the
     * karaoke punch word (the reported "just a word at the top" at 0:29). It must
     * fall back to the source video, keeping its own timing.
     */
    public function test_a_bare_overlay_animation_scene_falls_back_to_the_video(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 3, 'present' => 'split', 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
            ['start' => 3, 'end' => 9, 'present' => 'animation', 'punchWord' => 'cataloging', 'layers' => []],
        ]];

        $out = (new SceneVisualFiller)->showVideoOnBareScenes($plan);

        $this->assertSame('video', $out['scenes'][1]['present']);
        $this->assertSame(3, $out['scenes'][1]['start'], 'timing must not move — audio/karaoke are absolute');
        $this->assertSame(9, $out['scenes'][1]['end']);
        $this->assertSame('split', $out['scenes'][0]['present'], 'scenes that already have a visual are untouched');
    }
}
