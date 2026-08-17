<?php

namespace App\Services\Editor;

use App\Services\Shorts\ShortsException;
use Symfony\Component\Process\Process;

/**
 * Lays N transparent effect clips over the screen feed, each appearing at its
 * own moment, in ONE ffmpeg pass.
 *
 * The existing FfmpegVideoCompositor::overlay() puts one overlay over the whole
 * video; here each effect must show only for its own window, so the filter
 * shifts every overlay's timestamps to its start and gates it with `enable`.
 */
class SfxOverlayEngine
{
    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $timeout = 3600,
    ) {}

    /**
     * @param  array<int,array{path:string,at:float,duration:float}>  $placements
     */
    public function apply(string $base, array $placements, string $dest): string
    {
        if ($placements === []) {
            throw new ShortsException('No effects to place.');
        }

        @mkdir(dirname($dest), 0775, true);

        $process = new Process($this->buildOverlayArgs($base, $placements, $dest), timeout: $this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            throw new ShortsException('Effect overlay failed: '.mb_substr($err, -600));
        }

        return $dest;
    }

    /**
     * @param  array<int,array{path:string,at:float,duration:float}>  $placements
     * @return array<int,string>
     */
    public function buildOverlayArgs(string $base, array $placements, string $dest): array
    {
        $args = [$this->ffmpeg, '-y', '-i', $base];
        foreach ($placements as $p) {
            $args[] = '-i';
            $args[] = $p['path'];
        }

        $filtros = [];
        $corrente = '[0:v]';

        foreach (array_values($placements) as $i => $p) {
            $entrada = $i + 1;               // input 0 is the base video
            $at = $this->num((float) $p['at']);
            $ate = $this->num((float) $p['at'] + (float) $p['duration']);

            // Shift the effect's own timeline to the moment it should appear...
            $filtros[] = "[{$entrada}:v]setpts=PTS-STARTPTS+{$at}/TB[fx{$entrada}]";
            // ...then show it only inside its window. Without `enable` the last
            // frame would linger over the rest of the video.
            $saida = "[v{$entrada}]";
            $filtros[] = "{$corrente}[fx{$entrada}]overlay=0:0:enable='between(t,{$at},{$ate})':format=auto{$saida}";
            $corrente = $saida;
        }

        $args[] = '-filter_complex';
        $args[] = implode(';', $filtros);
        $args[] = '-map';
        $args[] = $corrente;
        // Keep the screen feed's own audio untouched.
        $args[] = '-map';
        $args[] = '0:a?';
        $args[] = '-c:a';
        $args[] = 'copy';
        $args[] = '-c:v';
        $args[] = 'libx264';
        $args[] = '-preset';
        $args[] = 'veryfast';
        $args[] = '-crf';
        $args[] = '20';
        $args[] = '-pix_fmt';
        $args[] = 'yuv420p';
        $args[] = $dest;

        return $args;
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0';
    }
}
