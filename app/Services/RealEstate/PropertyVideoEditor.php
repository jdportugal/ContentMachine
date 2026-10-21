<?php

namespace App\Services\RealEstate;

use App\Services\Clips\SceneVisualFiller;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Footage work for the property reel:
 *
 *  1. proxy(): stabilize (vid.stab, two passes) and colour-correct (adaptive
 *     levels, brighter, colour casts neutralised) BEFORE any AI sees a frame —
 *     the AI keeps whatever look it's given.
 *  2. assemble(): the sections the user marked, each transforming into its
 *     modern version (Kling) and played to its end, the finished room held, then
 *     a short blur dissolve to the next (no movement, no whoosh). The transformations'
 *     own sounds, the music and any narration mixed under it; never a minute.
 *
 * Frames are picked by index from the constant-frame-rate proxy, which is what
 * makes a variable speed curve a plain ffmpeg select.
 */
class PropertyVideoEditor
{
    private const OUT_FPS = 30;

    private const CLIP_VOLUME = 0.1;  // the animations' own sound, kept low under the music

    private const SHORT_SIDE = 1080;  // the reel is cut at 1080p (Kling's clips are 1080p)

    private const MAX_SECONDS = 58.0; // never a minute

    private const HOLD = 0.0;         // seconds the finished room is frozen before leaving it: none, the clip's own end blurs into the next

    private const HOLD_LAST = 0.0;    // …and at the very end of the reel

    private const WHIP = 0.35;        // seconds of the blur dissolve between two sections

    private const LEAD = 0.5;         // a transformation starts changing within this long after its cut

    /** The pace of the reel's moving moments: where each ramp lands and a transformation's camera. Real time. */
    public const PACE = 1.0;

    /**
     * Constant frame rate (60 when the phone shot 50+, else 30), timestamps from
     * 0, rotation applied, short side 1080, stabilized and colour-corrected, no
     * audio. Everything downstream — analysis, frames, the AI, the cut — reads it.
     */
    public function proxy(string $source, string $out): string
    {
        $p = new Process(['ffprobe', '-v', 'quiet', '-select_streams', 'v:0', '-show_entries', 'stream=avg_frame_rate', '-of', 'csv=p=0', $source]);
        $p->run();
        [$num, $den] = array_map('floatval', explode('/', trim($p->getOutput()).'/1'));
        $fps = $den > 0 && $num / $den >= 50 ? 60 : 30;

        $info = PropertyVideoAnalyzer::probe($source);
        $up = self::SHORT_SIDE / min($info['width'], $info['height']);
        $w = (int) (round($info['width'] * $up / 2) * 2);
        $h = (int) (round($info['height'] * $up / 2) * 2);
        // Stabilize at the footage's own size when it's under 1080p (5× less work
        // for WhatsApp'd 478p), else at 1080p; upscale after.
        $sw = $up > 1 ? (int) (floor($info['width'] / 2) * 2) : $w;
        $sh = $up > 1 ? (int) (floor($info['height'] / 2) * 2) : $h;
        $base = "select='gte(t,0)',setpts=PTS-STARTPTS,fps={$fps},scale={$sw}:{$sh}:flags=lanczos,setsar=1";

        @mkdir(dirname($out), 0775, true);
        $trf = $out.'.trf';
        try {
            // Pass 1: measure the shake.
            $this->run(['ffmpeg', '-y', '-v', 'error', '-i', $source, '-vf', "{$base},vidstabdetect=shakiness=6:accuracy=15:result={$trf}", '-an', '-f', 'null', '-']);
            // Pass 2: smooth the path (≈⅓ s window) with an adaptive zoom that only
            // crops while the camera actually shakes (a fixed zoom sized for the worst
            // moment shrinks every room) and corrections capped; colour at the
            // footage's own size (cheap): levels stretched per scene (smoothed over 2 s
            // so it never pumps, neutralising colour casts), brighter as real estate wants;
            // then up to 1080p.
            $smooth = $fps;
            $shift = (int) round(min($sw, $sh) * 0.06);
            $this->run(['ffmpeg', '-y', '-v', 'error', '-i', $source,
                '-vf', "{$base},vidstabtransform=input={$trf}:smoothing={$smooth}:optzoom=2:zoomspeed=0.1:maxshift={$shift}:maxangle=0.03:interpol=bicubic"
                    .',normalize=blackpt=black:whitept=white:smoothing='.($fps * 2).':independence=0.5:strength=0.7'
                    .',eq=brightness=0.03:contrast=1.05:saturation=1.15:gamma=1.12'
                    .',unsharp=5:5:'.($up > 1.2 ? '0.6' : '0.3')
                    .",scale={$w}:{$h}:flags=lanczos,format=yuv420p",
                '-an', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '18', '-g', (string) $fps, $out]);
        } finally {
            @unlink($trf);
        }

        return $out;
    }

    /**
     * A light copy for the section picker's player: 540p, 30 fps, the index up
     * front and a keyframe every half second, so the browser seeks to any frame
     * at once instead of downloading the whole 1080p60 proxy first.
     */
    public function preview(string $video, string $out): string
    {
        @mkdir(dirname($out), 0775, true);
        $this->run(['ffmpeg', '-y', '-v', 'error', '-i', $video, '-vf', 'fps=30,scale=-2:540', '-an',
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '27', '-g', '15', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', $out]);

        return $out;
    }

    /** Frames evenly across the whole walkthrough in one image, for the section picker's timeline. */
    public function filmstrip(string $video, string $out, int $frames = 60): string
    {
        $duration = PropertyVideoAnalyzer::probe($video)['duration'];
        @mkdir(dirname($out), 0775, true);
        $this->run(['ffmpeg', '-y', '-v', 'error', '-i', $video,
            '-vf', 'fps='.round($frames / max(1.0, $duration), 5).",scale=-2:96,tile={$frames}x1",
            '-frames:v', '1', '-q:v', '4', $out]);

        return $out;
    }

    /** The final formats on offer: label => width/height ratio ('source' keeps the footage's own). */
    public const ASPECTS = ['source' => 'Same as the footage', '9:16' => 'Vertical 9:16 (Reels, TikTok)', '4:5' => 'Portrait 4:5 (Instagram feed)', '1:1' => 'Square 1:1', '16:9' => 'Landscape 16:9'];

    /**
     * The reel's final size for $aspect, short side 1080, from footage of $w×$h.
     *
     * @return array{0:int,1:int}
     */
    public static function targetSize(string $aspect, int $w, int $h): array
    {
        if (! preg_match('/^(\d+):(\d+)$/', $aspect, $m)) {
            return [$w, $h];
        }
        $r = (int) $m[1] / (int) $m[2];

        return $r >= 1 ? [(int) (round(1080 * $r / 2) * 2), 1080] : [1080, (int) (round(1080 / $r / 2) * 2)];
    }

    /** Seconds each photo gets in a photo set's working copy (one section, one Kling clip each). */
    public const PHOTO_SECONDS = 5.0;

    /** How much of the frame the camera keeps at the near end of a photo's move (forward: the end; backward: the start). */
    private const PUSH = 0.85;

    /**
     * A photo set → a still working copy the rest of the pipeline reads like footage:
     * each photo held PHOTO_SECONDS, all at the first photo's shape (short side 1080,
     * centre-cropped), with the footage's colour correction.
     *
     * @param  list<string>  $photos
     */
    public function slideshow(array $photos, string $out): string
    {
        [$w0, $h0] = getimagesize($photos[0]) ?: [1920, 1080];
        $up = self::SHORT_SIDE / min($w0, $h0);
        $w = (int) (round($w0 * $up / 2) * 2);
        $h = (int) (round($h0 * $up / 2) * 2);
        $inputs = [];
        $chains = '';
        foreach ($photos as $i => $photo) {
            $inputs = array_merge($inputs, ['-loop', '1', '-framerate', '30', '-t', (string) self::PHOTO_SECONDS, '-i', $photo]);
            $chains .= "[{$i}:v]scale={$w}:{$h}:force_original_aspect_ratio=increase:flags=lanczos,crop={$w}:{$h},setsar=1,"
                .'eq=brightness=0.03:contrast=1.05:saturation=1.15:gamma=1.12,format=yuv420p'."[p{$i}];";
        }
        @mkdir(dirname($out), 0775, true);
        $this->run(array_merge(['ffmpeg', '-y', '-v', 'error'], $inputs, ['-filter_complex',
            $chains.implode('', array_map(fn ($i) => "[p{$i}]", array_keys($photos))).'concat=n='.count($photos).':v=1:a=0[v]',
            '-map', '[v]', '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '16', '-r', '30', '-g', '30', $out]));

        return $out;
    }

    /** $image with the camera moved in: the centre PUSH of it, back at full size. */
    public function push(string $image, string $out): string
    {
        $z = self::PUSH;
        @mkdir(dirname($out), 0775, true);
        $this->run(['ffmpeg', '-y', '-v', 'error', '-i', $image, '-vf',
            "crop=trunc(iw*{$z}/2)*2:trunc(ih*{$z}/2)*2,scale=trunc(iw/{$z}/2)*2:trunc(ih/{$z}/2)*2:flags=lanczos", '-frames:v', '1', '-q:v', '2', $out]);

        return $out;
    }

    /** A still of the footage at $t seconds. */
    public function frame(string $video, float $t, string $out): string
    {
        $t = max(0.0, min($t, PropertyVideoAnalyzer::probe($video)['duration'] - 0.05));
        @mkdir(dirname($out), 0775, true);
        $this->run(['ffmpeg', '-y', '-v', 'error', '-ss', (string) $t, '-i', $video, '-frames:v', '1', '-q:v', '2', $out]);

        return $out;
    }

    /**
     * @param  string  $video  the proxy
     * @param  list<array<string,mixed>>  $rooms  the user's sections; an animated one carries anim.clip (local mp4)
     * @param  array{path:string,words:list<array>,line:string}|null  $afterIntro  played as uploaded after the first section,
     *                                                                             starting on its line in the narration (words: the narration's transcript)
     */
    public function assemble(string $video, array $rooms, ?string $music, string $out, string $work, ?string $voice = null, ?float $musicVolume = null, ?string $outro = null, ?string $intro = null, string $aspect = 'source', ?array $afterIntro = null): string
    {
        $afterIntro = $afterIntro && is_file($afterIntro['path']) ? $afterIntro : null;
        $aiLen = $afterIntro ? PropertyVideoAnalyzer::probe($afterIntro['path'])['duration'] : 0.0;
        @mkdir($work, 0775, true);
        $info = PropertyVideoAnalyzer::probe($video);
        $fps = PropertyVideoAnalyzer::fps($video);
        foreach ($rooms as $i => $room) {
            if (! empty($room['anim']['clip'])) {
                $rooms[$i]['anim']['clip_seconds'] = PropertyVideoAnalyzer::probe($room['anim']['clip'])['duration'];
            }
        }
        // The outro card (4 s, minus its dissolve) is kept out of the minute first.
        // …and so is the after-intro.
        $plan = self::plan(array_values($rooms), $fps, ($outro && is_file($outro) ? 4.0 - self::WHIP : 0.0) + ($aiLen > 0 ? $aiLen - self::WHIP : 0.0));
        if ($plan['segments'] === []) {
            throw new RuntimeException('No usable footage to cut.');
        }

        [$w, $h] = [$info['width'], $info['height']];
        $fit = 'fps='.self::OUT_FPS.",scale={$w}:{$h}:force_original_aspect_ratio=increase:flags=lanczos,crop={$w}:{$h},setsar=1,format=yuv420p";
        $encode = ['-an', '-c:v', 'libx264', '-preset', 'medium', '-crf', '17', '-pix_fmt', 'yuv420p', '-r', (string) self::OUT_FPS, '-video_track_timescale', '15360'];

        $files = [];
        foreach ($plan['segments'] as $k => $seg) {
            $file = "{$work}/seg-{$k}.mp4";
            if ($seg['type'] === 'ai') {
                $this->aiPiece($seg + ['size' => "{$w}x{$h}"], $fit, $file, $encode);
            } else {
                $this->footage($video, $fps, $seg['chunks'], $file, "{$work}/seg-{$k}", $encode);
            }
            $files[] = $file;
        }

        // The chosen format: every section centre-cropped to it, at its size.
        [$tw, $th] = self::targetSize($aspect, $w, $h);
        if ([$tw, $th] !== [$w, $h]) {
            foreach ($files as $k => $f) {
                $cropped = "{$work}/fmt-{$k}.mp4";
                $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $f, '-vf',
                    "crop='min(iw,ih*{$tw}/{$th})':'min(ih,iw*{$th}/{$tw})',scale={$tw}:{$th}:flags=lanczos,setsar=1,format=yuv420p"], $encode, [$cropped]));
                $files[$k] = $cropped;
            }
            [$w, $h] = [$tw, $th];
            $fit = 'fps='.self::OUT_FPS.",scale={$w}:{$h}:force_original_aspect_ratio=increase:flags=lanczos,crop={$w}:{$h},setsar=1";
        }

        // The project's intro card over the start of the first section: rendered with a
        // pure-green photo panel (AnimatePropertyJob::intro()), keyed out so the clip plays through.
        if ($intro && is_file($intro) && $files !== []) {
            $first = "{$work}/first-with-intro.mp4";
            $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $files[0], '-i', $intro, '-filter_complex',
                "[1:v]scale={$w}:{$h},colorkey=0x00FF00:0.3:0.08,setpts=PTS-STARTPTS[o];[0:v][o]overlay=eof_action=pass:format=auto,format=yuv420p[v]",
                '-map', '[v]'], $encode, [$first]));
            $files[0] = $first;
        }

        // The after-intro follows the first section. The narration up to its line is said
        // over that section (held on its last frame if it needs longer); the line starts with the video.
        $voiceLen = $voice && is_file($voice) ? $this->duration($voice) : 0.0;
        $split = null;
        if ($afterIntro && $files !== []) {
            $first = $this->duration($files[0]) - self::WHIP;
            $split = $voiceLen > 0 ? self::afterIntroSplit($afterIntro['words'], $afterIntro['line'], $first - 0.3) : [0.0, 0.0];
            $hold = round(0.3 + $split[0] - $first, 3);
            if ($voiceLen > 0 && $hold > 0.05) {
                $held = "{$work}/first-held.mp4";
                $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $files[0], '-vf', "tpad=stop_mode=clone:stop_duration={$hold}"], $encode, [$held]));
                $files[0] = $held;
            }
            $clip = "{$work}/after-intro.mp4";
            $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $afterIntro['path'], '-vf', $fit], $encode, [$clip]));
            array_splice($files, 1, 0, [$clip]);
        }

        // The agent's outro card closes the reel, at the reel's size.
        if ($outro && is_file($outro)) {
            $card = "{$work}/outro.mp4";
            $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $outro, '-vf', $fit], $encode, [$card]));
            $files[] = $card;
        }

        // Join section to section with a blur dissolve.
        $joined = $files[0];
        $starts = [0.0];   // where each segment begins in the reel
        for ($k = 1; $k < count($files); $k++) {
            $starts[$k] = $starts[$k - 1] + $this->duration($files[$k - 1]) - self::WHIP;
            $next = "{$work}/join-{$k}.mp4";
            $this->dissolve($joined, $files[$k], $next, $encode);
            $joined = $next;
        }

        // With an after-intro the narration runs in three parts: up to the line, the line
        // (with the video, which plays on), and the rest once the video is over.
        $at = $starts[1] ?? 0.0;
        $parts = $split && $voiceLen > 0 ? [
            [0.0, $split[0], 0.3],
            [$split[0], $split[1], $at],
            [$split[1], $voiceLen, $at + max($split[1] - $split[0], $aiLen)],
        ] : [[0.0, $voiceLen, 0.3]];
        $voiceEnd = $voiceLen > 0 ? end($parts)[2] + end($parts)[1] - end($parts)[0] : 0.0;

        // Narration longer than the reel: the last finished room stays on until it ends.
        $length = $this->duration($joined);
        // Never cut the end (the outro is there): if the sections can't fit in a minute,
        // the reel runs long and the job says so.
        $seconds = max($length, min(self::MAX_SECONDS + $aiLen, $voiceEnd + 0.5));
        $pad = round($seconds - $length, 3);

        // Sound: each transformation's own audio (the things arriving), placed where
        // its change plays at 1×; the music under it; the narration on top.
        $inputs = ['-i', $joined];
        $mix = [];
        $n = 1;
        foreach ($plan['segments'] as $k => $seg) {
            if ($seg['type'] !== 'ai' || ! $this->hasAudio($seg['clip'])) {
                continue;
            }
            $at = (int) round(($starts[$k] + $seg['onset'] / $seg['leadSpeed']) * 1000);
            $inputs = array_merge($inputs, ['-i', $seg['clip']]);
            $mix[] = "[{$n}:a]atrim={$seg['onset']}:{$seg['seconds']},asetpts=PTS-STARTPTS"
                .($seg['speed'] != 1.0 ? ",atempo={$seg['speed']}" : '').",adelay={$at}|{$at},volume=".self::CLIP_VOLUME."[s{$n}]";
            $n++;
        }
        $labels = $n > 1 ? array_map(fn ($i) => "[s{$i}]", range(1, $n - 1)) : [];   // range(1, 0) counts down
        if ($music && is_file($music)) {
            $inputs = array_merge($inputs, ['-stream_loop', '-1', '-i', $music]);
            // The app's music volume is the level under a voiceover (default 0.1); with
            // no voiceover the music carries the reel, so it's raised in proportion.
            $level = round($voiceLen > 0 ? ($musicVolume ?? 0.15) : min(1.0, ($musicVolume ?? 0.1) * 5.5), 3);
            $mix[] = "[{$n}:a]atrim=0:{$seconds},volume={$level}"
                .',afade=t=in:d=0.5,afade=t=out:st='.max(0.0, round($seconds - 2, 2)).':d=2[m]';
            $labels[] = '[m]';
            $n++;
        }
        if ($voiceLen > 0) {
            $inputs = array_merge($inputs, ['-i', $voice]);
            $parts = array_values(array_filter($parts, fn ($q) => $q[1] - $q[0] > 0.01));
            $mix[] = "[{$n}:a]asplit=".count($parts).implode('', array_map(fn ($k) => "[v{$k}]", array_keys($parts)));
            foreach ($parts as $k => [$from, $to, $when]) {
                $ms = (int) round($when * 1000);
                $mix[] = "[v{$k}]atrim={$from}:{$to},asetpts=PTS-STARTPTS,adelay={$ms}|{$ms},volume=1.0[vo{$k}]";
                $labels[] = "[vo{$k}]";
            }
            $n++;
        }
        if ($afterIntro && $this->hasAudio($afterIntro['path'])) {
            $ms = (int) round($at * 1000);
            $inputs = array_merge($inputs, ['-i', $afterIntro['path']]);
            $mix[] = "[{$n}:a]adelay={$ms}|{$ms},volume=1.0[ai]";
            $labels[] = '[ai]';
            $n++;
        }

        @mkdir(dirname($out), 0775, true);
        $video = $pad > 0 ? "[0:v]tpad=stop_mode=clone:stop_duration={$pad}[v];" : '';
        if ($labels === []) {
            $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $joined], $pad > 0 ? ['-vf', "tpad=stop_mode=clone:stop_duration={$pad}", '-c:v', 'libx264', '-crf', '17'] : ['-c', 'copy'], ['-an', '-t', (string) $seconds, '-movflags', '+faststart', $out]));

            return $out;
        }
        $graph = $video.implode(';', $mix).';'.implode('', $labels).'amix=inputs='.count($labels).':normalize=0:duration=longest,alimiter=limit=0.95[a]';
        $this->run(array_merge(['ffmpeg', '-y', '-v', 'error'], $inputs, ['-filter_complex', $graph,
            '-map', $pad > 0 ? '[v]' : '0:v', '-map', '[a]'],
            $pad > 0 ? ['-c:v', 'libx264', '-preset', 'medium', '-crf', '17', '-pix_fmt', 'yuv420p'] : ['-c:v', 'copy'],
            ['-c:a', 'aac', '-b:a', '192k', '-t', (string) $seconds, '-movflags', '+faststart', $out]));

        return $out;
    }

    /**
     * Where the after-intro cuts into the narration, in its own time: [line start, line end]
     * when the line is heard (not in the first second), else a pause near $target, where the
     * narration simply stops for the video ([t, t]).
     *
     * @return array{0:float,1:float}
     */
    public static function afterIntroSplit(array $words, string $line, float $target): array
    {
        $span = trim($line) !== '' ? SceneVisualFiller::findLine($words, $line) : null;
        if ($span && $span[0] >= 1.0) {
            return $span;
        }
        $t = SceneVisualFiller::voiceBreak($words, max(1.0, $target), 1.0);

        return [$t, $t];
    }

    /**
     * A transformation, played to its end: its unchanged lead-in hurried (so the
     * room starts changing within LEAD of the cut), the change at the clip's own
     * speed, straight into the dissolve. Sections are joined
     * by dissolves (dissolve()), never by footage that could show a room as it was.
     *
     * @param  list<string>  $encode
     */
    private function aiPiece(array $seg, string $fit, string $out, array $encode): void
    {
        $len = $seg['seconds'];
        $speed = $seg['speed'];
        $onset = $seg['onset'];
        $lead = $seg['leadSpeed'];
        $hold = $seg['hold'];

        $graph = '';
        $parts = '';
        if ($onset > 0.05) {
            $graph .= '[0:v]split=2[x][y];';
            $graph .= "[x]trim=0:{$onset},setpts=(PTS-STARTPTS)/{$lead},{$fit}".($lead > 2 ? ',tmix=frames=3' : '').'[p0];';
            $parts .= '[p0]';
            $body = '[y]';
        } else {
            $body = '[0:v]';
        }
        $graph .= "{$body}trim={$onset}:{$len},setpts=(PTS-STARTPTS)/{$speed},{$fit}".($hold > 0 ? ",tpad=stop_mode=clone:stop_duration={$hold}" : '').'[p1];';
        $parts .= '[p1]';
        $graph .= $parts.'concat=n='.($onset > 0.05 ? 2 : 1).':v=1:a=0,setpts=PTS-STARTPTS[c];';
        $inputs = ['-t', (string) $len, '-i', $seg['clip']];
        // No push-in (zoompan's whole-pixel steps read as shaking).
        $graph .= "[c]scale=s={$seg['size']}:flags=lanczos,fps=".self::OUT_FPS.',format=yuv420p[v]';
        $this->run(array_merge(['ffmpeg', '-y', '-v', 'error'], $inputs, ['-filter_complex', $graph, '-map', '[v]'], $encode, [$out]));
    }

    /**
     * The cut from $a into $b: a short dissolve through blur — $a's tail blurs out
     * as $b comes in blurred and sharpens. No movement.
     *
     * @param  list<string>  $encode
     */
    private function dissolve(string $a, string $b, string $out, array $encode): void
    {
        $d = self::WHIP;
        $offset = max(0.0, round($this->duration($a) - $d, 3));
        // Fully blurred by the middle of the dissolve on both sides, so the swap happens inside the blur.
        $ramp = round($d * 0.6, 3);
        $blurIn = max(0.0, round($offset - 0.1, 3));
        $blurOut = round($d * 0.4, 3);
        $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-i', $a, '-i', $b, '-filter_complex',
            '[0:v]settb=AVTB,split[a0][a1];'
            ."[a1]format=yuva420p,gblur=sigma=40:enable='gte(t,{$blurIn})',fade=in:st={$blurIn}:d={$ramp}:alpha=1[ab];"
            .'[a0][ab]overlay=format=auto[x];'
            .'[1:v]settb=AVTB,split[b0][b1];'
            ."[b1]format=yuva420p,gblur=sigma=40:enable='lte(t,{$blurOut}+{$ramp})',fade=out:st={$blurOut}:d={$ramp}:alpha=1[bb];"
            .'[b0][bb]overlay=format=auto[y];'
            ."[x][y]xfade=transition=fade:duration={$d}:offset={$offset},format=yuv420p[v]",
            '-map', '[v]'], $encode, [$out]));
    }

    /**
     * A run of footage along its speed curve. Fast stretches get real motion
     * blur — the source frames that collapse into one output frame averaged
     * (tmix), as a camera would smear them — so it's cut in pieces by blur
     * strength, each decoded from a seek near its own start.
     *
     * @param  list<array{0:float,1:float,2:float}>  $chunks
     * @param  list<string>  $encode
     */
    private function footage(string $video, int $fps, array $chunks, string $out, string $prefix, array $encode): void
    {
        $band = fn (float $speed) => ($k = $speed * $fps / self::OUT_FPS) < 2 ? 1 : ($k < 5 ? 3 : 7);
        $groups = [];
        foreach ($chunks as $c) {
            $b = $band($c[2]);
            if ($groups !== [] && $groups[count($groups) - 1]['band'] === $b) {
                $groups[count($groups) - 1]['chunks'][] = $c;
            } else {
                $groups[] = ['band' => $b, 'chunks' => [$c]];
            }
        }

        $pieces = [];
        foreach ($groups as $g => $group) {
            // Seek a second early (the blur needs the frames before) and count frames from there.
            $from = max(0.0, floor($group['chunks'][0][0]) - 1.0);
            $local = array_map(fn ($c) => [$c[0] - $from, $c[1] - $from, $c[2]], $group['chunks']);
            $terms = [];
            foreach (self::runs(self::frameMap($local, $fps)) as [$a, $b]) {
                $terms[] = $a === $b ? "eq(n\\,{$a})" : "between(n\\,{$a}\\,{$b})";
            }
            if ($terms === []) {
                continue;
            }
            $graph = '[0:v]'.($group['band'] > 1 ? "tmix=frames={$group['band']}," : '')
                ."select='".implode('+', $terms)."',setpts=N/(".self::OUT_FPS.'*TB),format=yuv420p[v]';
            file_put_contents("{$prefix}-{$g}.txt", $graph);
            $piece = "{$prefix}-{$g}.mp4";
            $this->run(array_merge(['ffmpeg', '-y', '-v', 'error', '-ss', (string) $from, '-i', $video, '-filter_complex_script', "{$prefix}-{$g}.txt", '-map', '[v]'], $encode, [$piece]));
            $pieces[] = $piece;
        }

        file_put_contents("{$prefix}.txt", implode("\n", array_map(fn ($p) => "file '{$p}'", $pieces)));
        $this->run(['ffmpeg', '-y', '-v', 'error', '-f', 'concat', '-safe', '0', '-i', "{$prefix}.txt", '-c', 'copy', $out]);
    }

    /**
     * The reel as segments, one per section: its transformation — the unchanged
     * lead-in hurried, the change played to its end at the clip's own speed, the
     * finished room held — joined to the next by a dissolve (WHIP overlaps both),
     * so no real footage ever shows a modernised room as it was. A section without
     * a transformation plays as its own footage.
     *
     * @return array{segments:list<array<string,mixed>>,seconds:float}
     */
    public static function plan(array $rooms, int $fps, float $reserve = 0.0): array
    {
        $rooms = array_values(array_filter($rooms, fn ($r) => (float) $r['end'] - (float) $r['start'] > 0.3));
        if ($rooms === []) {
            return ['segments' => [], 'seconds' => 0.0];
        }
        $slow = max(self::OUT_FPS / $fps, self::PACE);   // where the ramps land
        $aiSeconds = fn ($r) => ! empty($r['anim']['clip']) ? max(1.5, (float) ($r['anim']['clip_seconds'] ?? 5) - 0.15) : 0.0;
        $ease = fn ($x) => $x * $x * (3 - 2 * $x);
        $step = 2 / self::OUT_FPS;
        // A transformation's length on screen: the hurried lead-in, the change played to
        // its end at the clip's own speed, and the hold.
        $aiOut = fn (array $s) => $s['onset'] / $s['leadSpeed'] + ($s['seconds'] - $s['onset']) / $s['speed'] + $s['hold'];

        $segments = [];
        $total = 0.0;
        $last = count($rooms) - 1;
        foreach ($rooms as $i => $room) {
            if ($aiSeconds($room) > 0) {
                $speed = 1.0;   // played to its end at its own speed
                $onset = min((float) ($room['anim']['onset'] ?? 0.0), $aiSeconds($room) - 1.0);
                $ai = ['type' => 'ai', 'clip' => $room['anim']['clip'], 'seconds' => round($aiSeconds($room), 3), 'speed' => $speed,
                    'last' => $i === $last,
                    'hold' => $i === $last ? self::HOLD_LAST : self::HOLD,
                    // The unchanged lead-in is hurried so the room starts changing within LEAD of the cut.
                    'onset' => $onset > self::LEAD ? round($onset, 3) : 0.0,
                    'leadSpeed' => $onset > self::LEAD ? round(max($speed, $onset / self::LEAD), 4) : $speed];
                $segments[] = $ai;
                $total += $aiOut($ai);
            } else {
                // No transformation: the section's own footage, in real time.
                $segments[] = ['type' => 'footage', 'chunks' => [[(float) $room['start'], (float) $room['end'], $slow]]];
                $total += ((float) $room['end'] - (float) $room['start']) / $slow;
            }
        }

        // Each dissolve overlaps the end of one section with the start of the next.
        $total -= self::WHIP * max(0, count($segments) - 1);

        // Over a minute (with $reserve kept for the outro): the transformations play a
        // little faster (<= 1.3x) rather than lose their end, then the holds get shorter.
        $budget = self::MAX_SECONDS - $reserve;
        // (Takes the segments as an argument: an arrow fn would keep the copy it was made with.)
        $aiTime = fn (array $segs) => array_sum(array_map(fn ($s) => $s['type'] === 'ai' ? $aiOut($s) : 0.0, $segs));
        $before = $aiTime($segments);
        $over = $total - $budget;
        if ($over > 0 && $before > 0) {
            $speedUp = min(1.3, $before / max(0.1, $before - $over));
            foreach ($segments as $k => $s) {
                if ($s['type'] === 'ai') {
                    $segments[$k]['speed'] = round($s['speed'] * $speedUp, 4);
                }
            }
            $total -= $before - $aiTime($segments);
        }
        $holds = array_sum(array_map(fn ($s) => $s['type'] === 'ai' ? max(0.0, $s['hold'] - 0.6) : 0.0, $segments));
        if ($total > $budget && $holds > 0) {
            $keep = max(0.0, 1 - ($total - $budget) / $holds);
            foreach ($segments as $k => $s) {
                if ($s['type'] === 'ai') {
                    $cut = max(0.0, $s['hold'] - 0.6) * (1 - $keep);
                    $segments[$k]['hold'] = round($s['hold'] - $cut, 3);
                    $total -= $cut;
                }
            }
        }

        return ['segments' => $segments, 'seconds' => round($total, 2)];
    }

    /**
     * Source frame index for each output frame (OUT_FPS), strictly increasing,
     * on one clock so rounding never drifts.
     *
     * @param  list<array{0:float,1:float,2:float}>  $chunks
     * @return list<int>
     */
    public static function frameMap(array $chunks, int $fps): array
    {
        $map = [];
        $clock = 0.0;
        $last = -1;
        foreach ($chunks as [$a, $b, $speed]) {
            $from = (int) round($clock * self::OUT_FPS);
            $clock += ($b - $a) / $speed;
            $to = (int) round($clock * self::OUT_FPS);
            for ($j = 0; $j < $to - $from; $j++) {
                $map[] = $last = max($last + 1, (int) floor(($a + $j * $speed / self::OUT_FPS) * $fps));
            }
        }

        return $map;
    }

    /**
     * @param  list<int>  $frames
     * @return list<array{0:int,1:int}> consecutive frame numbers collapsed into [from, to] runs
     */
    private static function runs(array $frames): array
    {
        $runs = [];
        foreach ($frames as $f) {
            if ($runs !== [] && $runs[count($runs) - 1][1] === $f - 1) {
                $runs[count($runs) - 1][1] = $f;
            } else {
                $runs[] = [$f, $f];
            }
        }

        return $runs;
    }

    private function hasAudio(string $file): bool
    {
        $p = new Process(['ffprobe', '-v', 'quiet', '-select_streams', 'a', '-show_entries', 'stream=index', '-of', 'csv=p=0', $file]);
        $p->run();

        return trim($p->getOutput()) !== '';
    }

    private function duration(string $file): float
    {
        $p = new Process(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0', $file]);
        $p->run();

        return (float) trim($p->getOutput());
    }

    /** @param list<string> $args */
    private function run(array $args): void
    {
        $p = new Process($args);
        $p->setTimeout(3600)->run();
        if (! $p->isSuccessful()) {
            throw new RuntimeException('ffmpeg failed: '.trim(substr($p->getErrorOutput(), -800)));
        }
    }
}
