<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\CliRemotionRenderer;
use App\Services\Shorts\LocalVideoEngine;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * OffthreadVideo decodes every frame at the SOURCE's resolution and hands it to
 * Chrome as a bitmap over its proxy. A 3456x2234 overlay source is ~31 MB a frame,
 * which Chrome refuses outright (net::ERR_FAILED, then EPIPE as the compositor
 * dies) — on frame one, whatever the disk or the concurrency. All of it was going
 * to be thrown away at the composition size anyway, so staging has to shrink it.
 */
class StagedVideoSizeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/cm-stage-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/public', 0775, true);
        config(['contentmachine.clips.remotion_path' => $this->dir]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** A real 1280x720 source staged into a 480x854 composition comes out fitted. */
    public function test_a_video_bigger_than_the_frame_is_staged_downscaled(): void
    {
        $source = $this->video(1280, 720);

        [$props, $staged] = (new CliRemotionRenderer)->stageProps([
            'width' => 480, 'height' => 854, 'videoSrc' => $source,
        ]);

        $meta = app(LocalVideoEngine::class)->probe($staged[0]);

        $this->assertSame(480, $meta['width'], 'the staged copy must fit the frame width');
        $this->assertSame(270, $meta['height'], '720 * (480/1280) = 270, aspect kept');
        $this->assertSame(basename($staged[0]), $props['videoSrc'], 'still rewritten to a basename');
    }

    /** A source already inside the frame is copied verbatim — no needless transcode. */
    public function test_a_video_within_the_frame_is_left_alone(): void
    {
        $source = $this->video(320, 240);

        [, $staged] = (new CliRemotionRenderer)->stageProps([
            'width' => 1080, 'height' => 1920, 'videoSrc' => $source,
        ]);

        $this->assertSame(filesize($source), filesize($staged[0]), 'byte-for-byte copy');
    }

    /** No frame in the props (VFX Lab sample renders) → the old plain-copy path. */
    public function test_without_a_frame_the_asset_is_copied(): void
    {
        $source = $this->dir.'/plain.mp4';
        file_put_contents($source, 'VIDEO');

        [, $staged] = (new CliRemotionRenderer)->stageProps(['videoSrc' => $source]);

        $this->assertSame('VIDEO', file_get_contents($staged[0]));
    }

    private function video(int $w, int $h): string
    {
        $path = $this->dir."/src-{$w}x{$h}.mp4";
        (new Process([
            config('services.shorts.ffmpeg', 'ffmpeg'), '-y',
            '-f', 'lavfi', '-i', "testsrc=size={$w}x{$h}:rate=25:duration=1",
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $path,
        ]))->setTimeout(120)->mustRun();

        return $path;
    }
}
