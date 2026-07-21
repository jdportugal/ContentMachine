<?php

namespace App\Services\Clips;

use App\Services\Clips\Contracts\VideoCompositor;
use RuntimeException;
use Symfony\Component\Process\Process;

class FfmpegVideoCompositor implements VideoCompositor
{
    public function extractAudio(string $videoPath, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        $this->run($this->buildExtractArgs($videoPath, $outPath));

        return $outPath;
    }

    public function overlay(string $baseVideo, string $overlay, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        $this->run($this->buildOverlayArgs($baseVideo, $overlay, $outPath));

        return $outPath;
    }

    public function probeDuration(string $videoPath): float
    {
        $process = new Process([
            'ffprobe', '-v', 'quiet', '-show_entries', 'format=duration',
            '-of', 'csv=p=0', $videoPath,
        ]);
        $process->run();

        return (float) trim($process->getOutput());
    }

    /** @return array<int,string> */
    public function buildExtractArgs(string $videoPath, string $outPath): array
    {
        return [
            'ffmpeg', '-y', '-i', $videoPath,
            '-vn', '-acodec', 'pcm_s16le', '-ar', '16000', '-ac', '1',
            $outPath,
        ];
    }

    /** @return array<int,string> */
    public function buildOverlayArgs(string $baseVideo, string $overlay, string $outPath): array
    {
        return [
            'ffmpeg', '-y', '-i', $baseVideo, '-i', $overlay,
            '-filter_complex', '[0:v][1:v]overlay=0:0:format=auto',
            '-c:a', 'copy',
            $outPath,
        ];
    }

    private function run(array $args): void
    {
        $process = new Process($args);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('ffmpeg falhou: '.$process->getErrorOutput());
        }
    }
}
