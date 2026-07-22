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

    public function splitStack(string $topVideo, string $bottomVideo, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);
        $this->run($this->buildSplitArgs($topVideo, $bottomVideo, $outPath));

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

    /** @return array<int,string> Good-quality AAC — used for both the render and Whisper (which accepts m4a). */
    public function buildExtractArgs(string $videoPath, string $outPath): array
    {
        return [
            'ffmpeg', '-y', '-i', $videoPath,
            '-vn', '-c:a', 'aac', '-b:a', '160k',
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

    /** @return array<int,string> top (animation) over bottom (source video); audio from bottom. */
    public function buildSplitArgs(string $topVideo, string $bottomVideo, string $outPath): array
    {
        $half = '[%d:v]scale=1080:960:force_original_aspect_ratio=increase,crop=1080:960,setsar=1';

        return [
            'ffmpeg', '-y', '-i', $topVideo, '-i', $bottomVideo,
            '-filter_complex',
            sprintf($half, 0).'[t];'.sprintf($half, 1).'[b];[t][b]vstack=inputs=2[v]',
            '-map', '[v]', '-map', '1:a?',
            '-c:a', 'aac', '-shortest',
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
