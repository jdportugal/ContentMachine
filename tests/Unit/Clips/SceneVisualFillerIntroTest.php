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
     * The reported flash: the intro revealed the image and then the SAME image
     * played full-frame for a beat.
     *
     * The intro takes the opening scene's own picture, but the leftover of that
     * scene used to be preserved verbatim — including the image-reveal that
     * supplied it. Real clips ended up with a 0.1s and a 0.4s sliver replaying the
     * identical image the instant the intro finished.
     */
    public function test_the_intro_image_is_not_replayed_by_the_leftover_scene(): void
    {
        // A long opening (6s) so the intro is capped and a remainder is split off.
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 6, 'layers' => [
                ['type' => 'image-reveal', 'params' => ['src' => 'gen_abc', 'variant' => 'fullscreen']],
            ]],
            ['start' => 6, 'end' => 8, 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
            ['start' => 8, 'end' => 10, 'layers' => [['type' => 'card', 'params' => ['title' => 'y']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['particle-image-assemble']);

        $this->assertSame('particle-image-assemble', $out['scenes'][0]['layers'][0]['type']);
        $this->assertSame('gen_abc', $out['scenes'][0]['layers'][0]['params']['src']);

        // Nowhere after the intro may that image appear again.
        foreach (array_slice($out['scenes'], 1) as $i => $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                $this->assertNotSame(
                    'gen_abc',
                    $layer['params']['src'] ?? null,
                    "scene {$i} replays the image the intro already showed",
                );
            }
        }

        // The image was the opening scene's only content, so no empty sliver is
        // left behind — the next scene simply starts when the intro ends.
        $this->assertSame($out['scenes'][0]['end'], $out['scenes'][1]['start']);
        $this->assertNotSame([], $out['scenes'][1]['layers']);
    }

    /** Content the intro did NOT take must still survive in the remainder. */
    public function test_other_opening_content_survives_the_intro(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 6, 'layers' => [
                ['type' => 'image-reveal', 'params' => ['src' => 'gen_abc']],
                ['type' => 'card', 'params' => ['title' => 'keep me']],
            ]],
            ['start' => 6, 'end' => 8, 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['particle-image-assemble']);

        $resto = $out['scenes'][1];
        $this->assertSame('card', $resto['layers'][0]['type']);
        $this->assertSame('keep me', $resto['layers'][0]['params']['title']);
        $this->assertCount(1, $resto['layers'], 'the intro image must not be among them');
    }

    /**
     * When the intro fell back to the LOGO, the opening scene's own image was
     * never consumed — it must not be stripped.
     */
    public function test_a_logo_intro_does_not_strip_the_scenes_unrelated_image(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 6, 'layers' => [['type' => 'card', 'params' => ['title' => 'hook']]]],
            ['start' => 6, 'end' => 8, 'layers' => [['type' => 'image-reveal', 'params' => ['src' => 'other']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp'], 'logo');

        $sobrevive = collect($out['scenes'])->pluck('layers')->flatten(1)
            ->contains(fn ($l) => ($l['params']['src'] ?? null) === 'other');
        $this->assertTrue($sobrevive, 'an image the intro never used must stay');
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

    /**
     * The reported bug: the user put an uploaded VIDEO on the opening scene, and
     * enforceIntro fed it to an image-only intro effect — which cannot render an
     * mp4, failing the whole render. The intro must instead become the built-in
     * `video-intro`, which plays that video full-frame.
     */
    public function test_a_video_on_the_opening_scene_promotes_the_intro_to_video_intro(): void
    {
        $plan = ['scenes' => [
            ['start' => 0, 'end' => 2, 'layers' => [['type' => 'image-reveal', 'params' => ['src' => 'vid_1', 'variant' => 'fullscreen']]]],
            ['start' => 2, 'end' => 4, 'layers' => [['type' => 'card', 'params' => ['title' => 'x']]]],
        ]];

        $out = (new SceneVisualFiller)->enforceIntro($plan, ['seal-stamp'], 'logo', ['vid_1']);

        $intro = $out['scenes'][0]['layers'][0];
        $this->assertSame('video-intro', $intro['type']);
        $this->assertSame('vid_1', $intro['params']['src']);
    }
}
