<?php

namespace App\Services\Capture;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Scroll-captures a web page to an mp4 the effects can play.
 *
 * Node drives the browser and writes a PNG sequence; ffmpeg turns it into a
 * video here, where the ffmpeg path and encoding settings already live. The
 * result is cached by url+size+length, so ten effects about the same product
 * capture the site once.
 */
class SiteCapture
{
    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly int $timeout = 600,
    ) {}

    /** Where captures are cached. Outside the vault: they are re-fetchable. */
    public function dir(): string
    {
        return (string) config('contentmachine.clips.site_captures', storage_path('app/clips/site-captures'));
    }

    public function path(string $url, int $width, int $height, float $seconds): string
    {
        $chave = substr(md5($url.'|'.$width.'x'.$height.'|'.$seconds), 0, 12);

        return $this->dir().'/site-'.$chave.'.mp4';
    }

    /**
     * One clean screenshot of the top of the page, cached like a capture.
     *
     * The same node script: its first frame is the un-scrolled page, so a still
     * is a two-frame run with no encoding step — right for a logo, an app UI or
     * a single hero, where six seconds of scrolling is noise.
     */
    public function still(string $url, int $width = 1920, int $height = 1080): string
    {
        $destino = $this->dir().'/site-'.substr(md5($url.'|'.$width.'x'.$height.'|still'), 0, 12).'.png';
        if (is_file($destino)) {
            return $destino;
        }

        @mkdir(dirname($destino), 0775, true);
        $framesDir = rtrim(sys_get_temp_dir(), '/').'/site-capture-'.bin2hex(random_bytes(6));

        try {
            // The script clamps frames to a minimum of two; frame 0 is the top.
            $this->recolherFrames($url, $framesDir, $width, $height, 2);
            if (! @copy($framesDir.'/frame-00000.png', $destino)) {
                throw new RuntimeException("The screenshot of {$url} produced no image.");
            }
        } finally {
            $this->limpar($framesDir);
        }

        return $destino;
    }

    /**
     * Capture $url, or return the cached file. Throws with the reason so the
     * caller can name the URL that failed rather than render an empty frame.
     */
    public function capture(string $url, int $width = 1920, int $height = 1080, float $seconds = 6.0, int $fps = 30): string
    {
        $destino = $this->path($url, $width, $height, $seconds);
        if (is_file($destino)) {
            return $destino;
        }

        @mkdir(dirname($destino), 0775, true);

        // Capture at half the output rate and let ffmpeg interpolate up: each
        // frame is a real screenshot (~80ms), so 30fps of stills would double the
        // capture time for motion the eye cannot resolve on a slow scroll.
        $captureFps = max(2, (int) round($fps / 2));
        $frames = max(2, (int) round($seconds * $captureFps));

        $framesDir = rtrim(sys_get_temp_dir(), '/').'/site-capture-'.bin2hex(random_bytes(6));

        try {
            $this->recolherFrames($url, $framesDir, $width, $height, $frames);
            $this->codificar($framesDir, $destino, $captureFps, $fps);
        } finally {
            $this->limpar($framesDir);
        }

        if (! is_file($destino)) {
            throw new RuntimeException("The capture of {$url} produced no video.");
        }

        return $destino;
    }

    private function recolherFrames(string $url, string $framesDir, int $width, int $height, int $frames): void
    {
        $script = rtrim((string) config('contentmachine.clips.remotion_path'), '/').'/scripts/capture-site.mjs';
        if (! is_file($script)) {
            throw new RuntimeException('The site capture script is missing.');
        }

        $process = new Process(
            ['node', $script, '--url', $url, '--out', $framesDir,
                '--width', (string) $width, '--height', (string) $height, '--frames', (string) $frames],
            rtrim((string) config('contentmachine.clips.remotion_path'), '/'),
            timeout: $this->timeout,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            $erro = trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'unknown error';
            throw new RuntimeException('Could not capture '.$url.': '.$this->razaoNode($erro));
        }
    }

    /**
     * The useful line of a node failure.
     *
     * Node puts the message FIRST and then a long stack, so keeping the last 300
     * characters kept only stack frames: a missing `puppeteer-core` reported
     * itself as "at ModuleLoader.resolve (node:internal/modules/esm/loader…)",
     * which names neither the cause nor the fix. Take the first line that is not a
     * stack frame instead, and only fall back to the tail if it is all frames.
     */
    private function razaoNode(string $stderr): string
    {
        $linhas = array_map('trim', preg_split('/\R/', $stderr) ?: []);
        $util = array_values(array_filter(
            $linhas,
            fn (string $l) => $l !== '' && ! str_starts_with($l, 'at ') && ! str_starts_with($l, '^')
        ));

        // The thrown Error line is the one that says what went wrong. Node prints a
        // "file:line" header and a source echo above it, so the FIRST useful line
        // is not it — prefer the line that actually carries the error.
        foreach ($util as $linha) {
            if (preg_match('/\b(Error|Warning|Cannot|ENOENT|ETIMEDOUT|ECONNREFUSED)\b/i', $linha)) {
                return mb_substr($linha, 0, 300);
            }
        }

        return $util !== [] ? mb_substr($util[0], 0, 300) : mb_substr($stderr, -300);
    }

    private function codificar(string $framesDir, string $destino, int $captureFps, int $fps): void
    {
        $process = new Process([
            $this->ffmpeg, '-y',
            '-framerate', (string) $captureFps,
            '-i', $framesDir.'/frame-%05d.png',
            // Smooth the half-rate capture up to the target rate.
            '-vf', "fps={$fps},format=yuv420p",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20',
            '-movflags', '+faststart',
            $destino,
        ], timeout: $this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Could not encode the capture: '.mb_substr(trim($process->getErrorOutput()), -300));
        }
    }

    private function limpar(string $dir): void
    {
        foreach (glob($dir.'/*.png') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
}
