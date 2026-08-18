<?php

namespace App\Services\Editor;

use App\Services\Shorts\ShortsException;
use Symfony\Component\Process\Process;

/**
 * Applies a set of keep-ranges to a video in ONE ffmpeg pass.
 *
 * Not N cuts plus a concat: `select`/`aselect` keep only the wanted spans and
 * `setpts`/`asetpts` renumber the timestamps so the gaps close. One re-encode,
 * no temp files, and the audio stays locked to the video.
 *
 * Both tracks are cut with the SAME ranges, which is what keeps the camera and
 * the screen feed in sync — they share a timeline, so identical ranges is the
 * entire sync mechanism.
 */
class MultiCutEngine
{
    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $timeout = 3600,
    ) {}

    /**
     * @param  array<int,array{0:float,1:float}>  $keepRanges
     */
    public function cut(string $source, array $keepRanges, string $dest): string
    {
        if ($keepRanges === []) {
            throw new ShortsException('Nothing left to keep — the cuts remove the whole recording.');
        }

        @mkdir(dirname($dest), 0775, true);

        $process = new Process($this->buildCutArgs($source, $keepRanges, $dest), timeout: $this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new ShortsException('Cut failed: '.mb_substr($err, -600));
        }

        return $dest;
    }

    /**
     * The argv, exposed so the filter can be asserted without running ffmpeg
     * (same approach as FfmpegVideoCompositor::buildOverlayArgs).
     *
     * @param  array<int,array{0:float,1:float}>  $keepRanges
     * @return array<int,string>
     */
    public function buildCutArgs(string $source, array $keepRanges, string $dest): array
    {
        $select = $this->selectExpression($keepRanges);

        return [
            $this->ffmpeg, '-y',
            '-i', $source,
            // N=frame index, FRAME_RATE/SR rebuild a continuous timeline from the
            // kept frames/samples; without setpts the gaps stay as frozen video.
            '-vf', "select='{$select}',setpts=N/FRAME_RATE/TB",
            '-af', "aselect='{$select}',asetpts=N/SR/TB",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '320k',
            '-movflags', '+faststart',
            $dest,
        ];
    }

    /**
     * `between(t,a,b)+between(t,c,d)` — ffmpeg's `+` is a logical OR here, so a
     * frame survives if it falls in any kept range.
     *
     * @param  array<int,array{0:float,1:float}>  $keepRanges
     */
    private function selectExpression(array $keepRanges): string
    {
        return implode('+', array_map(
            fn (array $r) => sprintf('between(t,%s,%s)', $this->num($r[0]), $this->num($r[1])),
            $keepRanges
        ));
    }

    /** Fixed-point with trailing zeros trimmed — ffmpeg expressions dislike 1.0E-5. */
    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0';
    }
}
