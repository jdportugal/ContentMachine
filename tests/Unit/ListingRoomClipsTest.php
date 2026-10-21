<?php

namespace Tests\Unit;

use App\Services\Clips\EffectLibrary;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use PHPUnit\Framework\TestCase;

class ListingRoomClipsTest extends TestCase
{
    /** A clip stays inside one shot, off the cut by a margin, 2–8 s long. */
    public function test_room_clips_stay_inside_one_shot(): void
    {
        $out = PropertyVideoAnalyzer::sanitizeClips([
            ['room' => 'Sala', 'description' => 'x', 'start' => 9.5, 'end' => 14],   // crosses the cut at 10
            ['room' => 'Cozinha', 'description' => 'y', 'start' => 18, 'end' => 40],   // too long
            ['room' => 'WC', 'description' => 'z', 'start' => 29.9, 'end' => 31],      // too short
        ], 60.0, [10.0, 30.0]);

        $this->assertSame([10.4, 14.0], [$out[0]['start'], $out[0]['end']]);
        $this->assertSame([18.0, 28.0], [$out[1]['start'], $out[1]['end']]);
        $this->assertCount(2, $out);
    }

    /** Effects written for photos play a provided video in the same slot. */
    public function test_effects_get_the_video_aware_img(): void
    {
        $tsx = "import React from \"react\";\nimport { AbsoluteFill, Img, useCurrentFrame } from 'remotion';\n<Img src={s} />";

        $out = EffectLibrary::videoAware($tsx);

        $this->assertStringContainsString('import { AbsoluteFill, useCurrentFrame } from "remotion";', $out);
        $this->assertStringContainsString('import { MediaImg as Img } from "../media";', $out);
        $this->assertSame('import { AbsoluteFill } from "remotion";', EffectLibrary::videoAware('import { AbsoluteFill } from "remotion";'));
    }

    /** The close look trims the doorway at the start and drops a clip with no clean stretch. */
    public function test_tile_picks_trim_the_clips(): void
    {
        $clips = [
            ['room' => 'Sala', 'description' => '', 'start' => 10.0, 'end' => 16.0],
            ['room' => 'WC', 'description' => '', 'start' => 20.0, 'end' => 24.0],
        ];

        $out = PropertyVideoAnalyzer::trimClips($clips, [['clip' => 1, 'first' => 3, 'last' => 10, 'keep' => true], ['clip' => 2, 'keep' => false]]);

        $this->assertSame([[11.0, 15.0]], array_map(fn ($c) => [$c['start'], $c['end']], $out));
    }

    /** Two clips of one room never share footage. */
    public function test_room_clips_never_overlap(): void
    {
        $out = PropertyVideoAnalyzer::sanitizeClips([
            ['room' => 'Sala', 'description' => '', 'start' => 10, 'end' => 16],
            ['room' => 'Sala 2', 'description' => '', 'start' => 14, 'end' => 20],
        ], 60.0);

        $this->assertSame([[10.0, 16.0], [16.0, 20.0]], array_map(fn ($c) => [$c['start'], $c['end']], $out));
    }

    /** The fallback keeps every shot of 1.5 s or more, long ones in pieces of at most 6 s. */
    public function test_shot_clips_keep_what_a_choppy_video_has(): void
    {
        // The 18 s walkthrough that failed the import: six cuts, one long shot.
        $out = PropertyVideoAnalyzer::shotClips(17.83, [1.07, 2.2, 9.73, 11.73, 13.83, 16.1]);

        $this->assertSame(
            [[2.4, 5.97], [5.97, 9.53], [9.93, 11.53], [11.93, 13.63], [14.03, 15.9], [16.3, 17.83]],
            array_map(fn ($c) => [$c['start'], $c['end']], $out),
        );
    }
}
