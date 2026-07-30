<?php

namespace App\Services\Shorts;

use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * LOCAL and independent video-editing engine — the reimplementation, with
 * ffmpeg/ffprobe (and Whisper via a Python script), of the operations that the
 * Flask "ShortsCreator" service did over HTTP:
 *
 *   transcribe()     ← /generate-subtitles  (Whisper, word by word)
 *   split()          ← /split-video         (cut [inicio,fim])
 *   burnSubtitles()  ← /add-subtitles        (burns ASS/libass subtitles)
 *   addMusic()       ← /add-music            (mixes background music)
 *
 * There are no async jobs or polling: each method runs synchronously and
 * blocking and returns the path of the produced file. The same
 * cut→subtitles→music logic as the n8n flow, but without an external API.
 */
class LocalVideoEngine
{
    public function __construct(
        private readonly string $ffmpeg = 'ffmpeg',
        private readonly string $ffprobe = 'ffprobe',
        private readonly string $python = 'python3',
        private readonly string $transcribeScript = '',
        private readonly string $whisperModel = 'tiny',
        private readonly string $fontsDir = '',
        private readonly int $timeout = 1800,
    ) {}

    public function fontsDir(): string
    {
        return $this->fontsDir;
    }

    /**
     * Resolves a video reference to a readable local path.
     * Accepts an absolute local path (used directly) or an http(s) URL
     * (downloaded to $tempDir). Returns the local path.
     */
    public function resolveSource(string $ref, string $tempDir): string
    {
        if (preg_match('#^https?://#i', $ref)) {
            @mkdir($tempDir, 0775, true);
            $dest = rtrim($tempDir, '/').'/source-'.md5($ref).'.mp4';

            if (! is_file($dest)) {
                $body = Http::timeout($this->timeout)->get($ref)->throw()->body();
                file_put_contents($dest, $body);
            }

            return $dest;
        }

        if (! is_file($ref)) {
            throw new ShortsException("Video file not found: {$ref}");
        }

        return $ref;
    }

    /**
     * ffprobe → video metadata.
     *
     * @return array{duration:float,width:int,height:int,has_audio:bool}
     */
    public function probe(string $path): array
    {
        $out = $this->run([
            $this->ffprobe, '-v', 'error',
            '-show_entries', 'format=duration',
            '-show_entries', 'stream=width,height,codec_type',
            '-of', 'json', $path,
        ]);

        $data = json_decode($out, true) ?: [];
        $duration = (float) ($data['format']['duration'] ?? 0);
        $width = 0;
        $height = 0;
        $hasAudio = false;

        foreach (($data['streams'] ?? []) as $s) {
            if (($s['codec_type'] ?? '') === 'video' && $width === 0) {
                $width = (int) ($s['width'] ?? 0);
                $height = (int) ($s['height'] ?? 0);
            }
            if (($s['codec_type'] ?? '') === 'audio') {
                $hasAudio = true;
            }
        }

        return ['duration' => $duration, 'width' => $width, 'height' => $height, 'has_audio' => $hasAudio];
    }

    /**
     * Cuts [startSec, endSec] from the original video, re-encoding (libx264 +
     * aac 320k), like the original's split_video.
     */
    public function split(string $source, float $startSec, float $endSec, string $dest): string
    {
        $meta = $this->probe($source);
        $start = max(0.0, $startSec);
        $end = min($endSec, $meta['duration'] > 0 ? $meta['duration'] : $endSec);

        if ($start >= $end) {
            throw new ShortsException('The clip start must be before the end.');
        }

        @mkdir(dirname($dest), 0775, true);

        $this->run([
            $this->ffmpeg, '-y',
            '-ss', $this->num($start),
            '-i', $source,
            '-t', $this->num($end - $start),
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
            '-c:a', 'aac', '-b:a', '320k',
            '-movflags', '+faststart',
            $dest,
        ]);

        return $dest;
    }

    /**
     * Burns the subtitles (subtitle_data + style + word mode) into the already
     * cut clip, via libass. This is the re-runnable step of the "Regenerate" button.
     *
     * @param  array<int,array<string,mixed>>  $subtitleData
     * @param  array<string,mixed>  $settings
     */
    public function burnSubtitles(string $clipPath, array $subtitleData, array $settings, string $wordMode, string $dest): string
    {
        $meta = $this->probe($clipPath);
        $w = $meta['width'] > 0 ? $meta['width'] : 1080;
        $h = $meta['height'] > 0 ? $meta['height'] : 1920;

        @mkdir(dirname($dest), 0775, true);

        $ass = (new AssSubtitleBuilder)->build($subtitleData, $settings, $wordMode, $w, $h);
        $assPath = dirname($dest).'/subtitles.ass';
        file_put_contents($assPath, $ass);

        $filter = 'ass='.$this->filterArg($assPath);
        if ($this->fontsDir !== '') {
            $filter .= ':fontsdir='.$this->filterArg($this->fontsDir);
        }

        $this->run([
            $this->ffmpeg, '-y',
            '-i', $clipPath,
            '-vf', $filter,
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
            '-c:a', 'copy',
            '-movflags', '+faststart',
            $dest,
        ]);

        return $dest;
    }

    /**
     * Mixes background music into the video (volume, fades, loop), like the
     * original's add_music_to_video.
     *
     * @param  array{volume?:float,fade_in?:float,fade_out?:float,loop_music?:bool}  $settings
     */
    public function addMusic(string $videoPath, string $musicPath, array $settings, string $dest): string
    {
        $meta = $this->probe($videoPath);
        $dur = $meta['duration'];
        $vol = (float) ($settings['volume'] ?? 0.5);
        $fadeIn = (float) ($settings['fade_in'] ?? 0);
        $fadeOut = (float) ($settings['fade_out'] ?? 0);
        $loop = (bool) ($settings['loop_music'] ?? false);

        @mkdir(dirname($dest), 0775, true);

        // Music repetition: a FINITE number of repeats (like the original:
        // ceil(video_duration / music_duration)). A -stream_loop -1 (infinite)
        // would keep ffmpeg alive forever after writing the output.
        $streamLoop = 0;
        if ($loop && $dur > 0) {
            $musicDur = $this->probe($musicPath)['duration'];
            if ($musicDur > 0 && $musicDur < $dur) {
                $streamLoop = (int) ceil($dur / $musicDur) - 1; // -stream_loop N = N+1 reads
            }
        }

        // Music filter chain: volume → fades → atrim (limits to the video
        // duration, so the output ends exactly at the end of the video).
        $music = "[1:a]volume={$this->num($vol)}";
        if ($fadeIn > 0) {
            $music .= ",afade=t=in:st=0:d={$this->num($fadeIn)}";
        }
        if ($fadeOut > 0 && $dur > 0) {
            $music .= ',afade=t=out:st='.$this->num(max(0, $dur - $fadeOut)).':d='.$this->num($fadeOut);
        }
        if ($dur > 0) {
            $music .= ',atrim=0:'.$this->num($dur).',asetpts=N/SR/TB';
        }
        $music .= '[m]';

        $cmd = [$this->ffmpeg, '-y', '-i', $videoPath];
        if ($streamLoop > 0) {
            $cmd[] = '-stream_loop';
            $cmd[] = (string) $streamLoop;
        }
        $cmd[] = '-i';
        $cmd[] = $musicPath;

        if ($meta['has_audio']) {
            $filter = $music.';[0:a][m]amix=inputs=2:duration=first:normalize=0[a]';
            $map = ['-map', '0:v', '-map', '[a]'];
        } else {
            $filter = $music;
            $map = ['-map', '0:v', '-map', '[m]'];
        }

        $cmd = array_merge($cmd, [
            '-filter_complex', $filter,
            ...$map,
            '-c:v', 'copy',
            '-c:a', 'aac', '-b:a', '320k',
            '-movflags', '+faststart',
            $dest,
        ]);

        $this->run($cmd);

        return $dest;
    }

    /**
     * Transcribes the full video with Whisper (via scripts/transcribe.py) and
     * returns the subtitle_data in the same format as the original:
     * [{start,end,text,words:[{word,start,end}]}].
     *
     * @return array<int,array<string,mixed>>
     */
    public function transcribe(string $videoPath, string $language = 'pt'): array
    {
        if ($this->transcribeScript === '' || ! is_file($this->transcribeScript)) {
            throw new ShortsException('Transcription script not found ('.$this->transcribeScript.').');
        }

        $out = $this->run([
            $this->python, $this->transcribeScript,
            '--input', $videoPath,
            '--language', $language,
            '--model', $this->whisperModel,
        ], $this->timeout);

        $data = json_decode($out, true);

        if (! is_array($data)) {
            throw new ShortsException('Invalid transcript (the script did not return JSON).');
        }

        // The script may return {subtitle_data:[...]} or the array directly.
        return $data['subtitle_data'] ?? $data;
    }

    // --- Infra --------------------------------------------------------

    /** Runs a process and returns stdout; throws ShortsException on failure. */
    private function run(array $command, ?int $timeout = null): string
    {
        $process = new Process($command, timeout: $timeout ?? $this->timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $err = trim($process->getErrorOutput()) ?: trim($process->getOutput());
            $bin = $command[0] ?? 'process';
            throw new ShortsException("Failure in {$bin}: ".mb_substr($err, -600));
        }

        return $process->getOutput();
    }

    /** Escapes a path for an ffmpeg filter argument value (-vf/-filter_complex). */
    private function filterArg(string $path): string
    {
        // Inside libass single quotes: escape \ : ' and wrap in ''.
        $escaped = str_replace(['\\', ':', "'"], ['\\\\', '\\:', "\\'"], $path);

        return "'".$escaped."'";
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0';
    }
}
