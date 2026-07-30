<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\VideoCompositor;

class FakeVideoCompositor implements VideoCompositor
{
    public function extractAudio(string $videoPath, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, 'FAKE-WAV');

        return $outPath;
    }

    public function overlay(string $baseVideo, string $overlay, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, 'FAKE-FINAL');

        return $outPath;
    }

    public function splitStack(string $topVideo, string $bottomVideo, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, 'FAKE-SPLIT');

        return $outPath;
    }

    public function probeDuration(string $videoPath): float
    {
        return 3.0;
    }
}
