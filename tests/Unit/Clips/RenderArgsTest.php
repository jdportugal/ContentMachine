<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\CliRemotionRenderer;
use App\Services\Clips\FfmpegVideoCompositor;
use PHPUnit\Framework\TestCase;

class RenderArgsTest extends TestCase
{
    public function test_opaque_render_uses_h264(): void
    {
        $args = (new CliRemotionRenderer)->buildRenderArgs(
            ['transparent' => false], '/out/clip.mp4', '/tmp/p.json'
        );

        $this->assertContains('--codec=h264', $args);
        $this->assertContains('ClipComposition', $args);
        $this->assertContains('--props=/tmp/p.json', $args);
        $this->assertNotContains('--codec=prores', $args);
    }

    public function test_transparent_render_uses_prores_4444(): void
    {
        $args = (new CliRemotionRenderer)->buildRenderArgs(
            ['transparent' => true], '/out/overlay.mov', '/tmp/p.json'
        );

        $this->assertContains('--codec=prores', $args);
        $this->assertContains('--prores-profile=4444', $args);
    }

    public function test_overlay_args_compose_two_inputs(): void
    {
        $args = (new FfmpegVideoCompositor)->buildOverlayArgs('/a/base.mp4', '/a/over.mov', '/a/final.mp4');

        $this->assertSame('ffmpeg', $args[0]);
        $this->assertContains('/a/base.mp4', $args);
        $this->assertContains('/a/over.mov', $args);
        $this->assertContains('[0:v][1:v]overlay=0:0:format=auto', $args);
    }

    public function test_extract_args_drop_video(): void
    {
        $args = (new FfmpegVideoCompositor)->buildExtractArgs('/a/in.mp4', '/a/out.wav');

        $this->assertContains('-vn', $args);
        $this->assertContains('/a/out.wav', $args);
    }
}
