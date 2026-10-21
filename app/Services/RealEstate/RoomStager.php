<?php

namespace App\Services\RealEstate;

use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * The modernised still of a room — an edit of its real end frame that Kling
 * animates towards — with the room's architecture kept true:
 *
 *  1. Claude inventories the openings in the real frame (doors, doorways,
 *     corridors, windows, stairs, built-ins), each with where it is; the list
 *     goes into the edit prompt (and Kling's) as things that must not change.
 *  2. A few candidates are made with an edit model that keeps the framing.
 *  3. Claude checks every candidate against the real frame, item by item: a
 *     door gone, a corridor turned into a window, an opening that isn't real.
 *     The best clean candidate wins (closest framing, SSIM); if none is clean,
 *     a second round is told exactly which mistakes to avoid, and the one with
 *     the fewest problems overall is kept and its problems shown.
 */
class RoomStager
{
    use RunsClaudeCli;

    private const CANDIDATES = 3;

    private const ROUNDS = 2;

    /** Below this the camera itself has visibly moved (a modernised room scores ~0.55 when it hasn't). */
    public const DRIFT = 0.42;

    protected function passo(): string
    {
        return 'clips_plano';
    }

    /**
     * The best still, plus every candidate made (best first) for the user to choose from.
     *
     * @return array{path:string,score:float,keep:list<string>,problems:list<string>,candidates:list<array{path:string,score:float,problems:list<string>}>}
     */
    public function stage(array $property, array $anim): array
    {
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $kie = app(KieClient::class);
        $kie->passo = 'clips_imagens';
        if (! $kie->configurado()) {
            throw new RuntimeException('kie.ai key missing (Settings → Steps → clip images).');
        }

        $frame = $disk->path($anim['end_frame']);
        $keep = $anim['keep'] ?? $this->inventory($frame);
        $ref = $kie->upload((string) file_get_contents($frame), 'frame.jpg');
        $aspect = PropertyVideoAnalyzer::aspect((int) $property['width'], (int) $property['height']);

        // Up to two rounds: if even the best of the first has problems, the second
        // is told exactly which mistakes to avoid.
        $best = null;
        $avoid = [];
        $error = null;
        $all = [];
        for ($round = 0; $round < self::ROUNDS && ($best === null || $best['problems'] !== []); $round++) {
            $prompt = PropertyVideoAnalyzer::stillPrompt($anim, $keep)
                .($avoid !== [] ? ' A previous attempt made these mistakes — do not repeat them: '.implode('; ', $avoid).'.' : '');
            $tasks = array_map(fn () => $kie->submitExactEdit($prompt, $ref, $aspect), range(1, self::CANDIDATES));

            $made = [];
            foreach ($tasks as $task) {
                try {
                    $path = 'clips/uploads/'.Str::random(40).'.png';
                    $disk->put($path, Http::timeout(120)->get($kie->poll($task))->throw()->body());
                    $made[] = ['path' => $path, 'score' => self::alignment($frame, $disk->path($path))];
                } catch (\Throwable $e) {
                    $error = $e;
                }
            }
            if ($made === []) {
                continue;
            }

            $problems = $this->verify($frame, array_map(fn ($c) => $disk->path($c['path']), $made), $keep, (string) $anim['prompt'], (array) ($anim['cleanup'] ?? []));
            foreach ($made as $n => $c) {
                $made[$n]['problems'] = $problems[$n] ?? [];
            }
            // Clean first, then fewest problems; closest framing breaks ties. Every one is kept to choose from.
            $all = array_merge($all, $made);
            usort($all, fn ($a, $b) => [count($a['problems']), -$a['score']] <=> [count($b['problems']), -$b['score']]);
            $best = $all[0];
            $avoid = $best['problems'];
        }

        if ($best === null) {
            throw $error ?? new RuntimeException('No modernised still came back.');
        }

        return $best + ['keep' => $keep, 'candidates' => $all];
    }

    /**
     * The openings and fixed architecture in the real frame, each with where it
     * is — what an edit must not touch.
     *
     * @return list<string>
     */
    public function inventory(string $frame): array
    {
        $prompt = <<<PROMPT
        Look at this photo of a room: {$frame}
        List the openings and major fixed architecture a renovation must NOT change, each with where it is in the frame
        (left / centre / right, near / back wall, edge of frame) and its state:
        every door (open or closed), every doorway or opening to another room, every corridor or hallway seen through an
        opening, every window and glass door, every staircase, and large built-ins (wardrobes, cabinet walls,
        fireplaces). Be literal: an opening to a corridor is "an open doorway to a corridor", not a door or a window.
        Leave out small things a renovation may cover or replace: sockets, switches, vents, boxes, light fittings,
        skirting, floors, ceilings, paint. Don't list what is absent.
        Return ONLY JSON: {"keep": ["the open doorway to a corridor on the right edge, back wall", "…"]}
        PROMPT;

        try {
            $out = (string) ($this->runClaude($prompt, null, ['images' => [$frame], 'maxTurns' => 4, 'timeout' => 300])['result'] ?? '');
            $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;

            return array_values(array_filter(array_map('trim', (array) ($json['keep'] ?? [])), 'strlen'));
        } catch (\Throwable $e) {
            Log::warning('Room inventory failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Claude's item-by-item check of each candidate against the real frame.
     *
     * @param  list<string>  $candidates
     * @param  list<string>  $keep
     * @return array<int,list<string>> problems per candidate index (empty = clean)
     */
    public function verify(string $frame, array $candidates, array $keep, string $brief = '', array $cleanup = []): array
    {
        $files = collect($candidates)->map(fn ($f, $i) => 'Edit '.($i + 1).": {$f}")->implode("\n");
        $list = $keep === [] ? '(none listed — work them out from the real photo)' : '- '.implode("\n- ", $keep);
        $prompt = <<<PROMPT
        The real room: {$frame}
        AI edits of it that should only modernise furniture, décor and finishes:
        {$files}

        These must stay exactly as they are in every edit — same place, same size, same kind of thing:
        {$list}

        Open every image. For each edit, list every problem:
        - an item above missing, moved, resized, or turned into something else (a corridor opening turned into a window
          or a wall, a doorway closed off, a door removed, a window bricked up or moved);
        - any door, window, doorway or opening that is NOT in the real photo;
        - furniture placed in front of or inside a door, doorway or window;
        - the camera framing noticeably different from the real photo (a different angle, or the room re-composed —
          not a slight crop);
        - anything unfinished or messy still showing — the room must look finished and move-in ready: a loose object,
          tool, ladder, bucket, box, rubbish or building material left; a picture frame or board still leaning on a wall;
          an exposed or dangling wire or cable, an open junction box, a bare bulb on a cable; raw, patched or unpainted
          plaster or concrete, holes, cracks, stains or tape; dust, rubble or paint splashes. Name each one you see.
        Paint, flooring, furniture, lighting and décor changing is expected — not a problem; so is furniture hiding a
        socket, switch, vent or small fixture, and so is an opening getting finished (raw brick or broken plaster around
        it made into clean, straight, painted edges) as long as it stays an opening of the same size and place.
        PROMPT
            .($cleanup !== [] ? "\nIn the real photo these needed clearing or finishing — flag any still there: ".implode('; ', $cleanup).'.' : '')
            .(stripos($brief, 'laminate') !== false ? "\n- the floor must have become new warm light-oak laminate everywhere it shows: flag any of the old floor still visible." : '')
            .<<<'PROMPT'

        Return ONLY JSON: {"edits": [{"n": 1, "problems": ["…"]}, …]} with an empty list for a clean edit.
        PROMPT;

        try {
            $out = (string) ($this->runClaude($prompt, null, ['images' => array_merge([$frame], $candidates), 'maxTurns' => count($candidates) + 4, 'timeout' => 600])['result'] ?? '');
            $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;
            $byIndex = [];
            foreach ((array) ($json['edits'] ?? []) as $e) {
                $byIndex[(int) ($e['n'] ?? 0) - 1] = array_values(array_filter(array_map('trim', (array) ($e['problems'] ?? [])), 'strlen'));
            }

            return $byIndex;
        } catch (\Throwable $e) {
            Log::warning('Room check failed: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Claude's check of a Kling clip: five frames across it, against the real
     * first frame and the approved still. Video models invent motion — a door
     * that wasn't there swinging open is the typical failure.
     *
     * @param  list<string>  $keep
     * @return list<string> problems (empty = clean)
     */
    public function verifyClip(string $startFrame, string $still, string $clip, array $keep): array
    {
        $dir = sys_get_temp_dir().'/clipcheck-'.Str::random(8);
        @mkdir($dir, 0775, true);
        try {
            $p = new Process(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0', $clip]);
            $p->run();
            $d = max(0.5, (float) trim($p->getOutput()));
            $frames = [];
            foreach ([0.2, 0.4, 0.6, 0.8, 0.97] as $n => $at) {
                $f = "{$dir}/frame-".($n + 1).'.jpg';
                (new Process(['ffmpeg', '-nostdin', '-y', '-v', 'error', '-ss', (string) round($d * $at, 2), '-i', $clip, '-frames:v', '1', '-vf', 'scale=960:-2', '-q:v', '3', $f]))->run();
                if (is_file($f)) {
                    $frames[] = $f;
                }
            }
            if ($frames === []) {
                return [];
            }
            $list = $keep === [] ? '(work them out from the real first frame)' : '- '.implode("\n- ", $keep);
            $seq = implode("\n", array_map(fn ($f, $i) => 'Frame '.($i + 1).": {$f}", $frames, array_keys($frames)));
            $prompt = <<<PROMPT
            A video model animated a room being modernised, from its real first frame to an approved final still.
            Real first frame: {$startFrame}
            Approved final still: {$still}
            Frames from the video, in order:
            {$seq}

            The room's openings, which must never change during the video:
            {$list}

            Open every image. List every problem in the video frames:
            - a door, doorway, opening or window that is in neither the first frame nor the final still;
            - a door that opens, closes or swings, or an opening that closes up, moves or turns into something else;
            - walls, ceiling or windows warping or morphing (beyond the camera's own movement).
            New furniture, décor, lighting and the floor changing are expected — not problems.
            Return ONLY JSON: {"problems": ["…"]} — an empty list if the video is clean.
            PROMPT;
            $out = (string) ($this->runClaude($prompt, null, ['images' => array_merge([$startFrame, $still], $frames), 'maxTurns' => count($frames) + 5, 'timeout' => 600])['result'] ?? '');
            $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;

            return array_values(array_filter(array_map('trim', (array) ($json['problems'] ?? [])), 'strlen'));
        } catch (\Throwable $e) {
            Log::warning('Clip check failed: '.$e->getMessage());

            return [];
        } finally {
            array_map('unlink', glob("{$dir}/*") ?: []);
            @rmdir($dir);
        }
    }

    /**
     * When a transformation starts changing the room, in seconds of the clip:
     * Claude looks at frames every half second through its first 6 s and names
     * the first one where anything new shows (furniture, floor, décor, light).
     * The editor hurries the unchanged lead-in. 0 when it can't tell.
     */
    public function changeStart(string $clip): float
    {
        $dir = sys_get_temp_dir().'/changestart-'.Str::random(8);
        @mkdir($dir, 0775, true);
        try {
            $p = new Process(['ffprobe', '-v', 'quiet', '-show_entries', 'format=duration', '-of', 'csv=p=0', $clip]);
            $p->run();
            $d = (float) trim($p->getOutput());
            $times = range(0.0, min(6.0, max(0.0, $d - 1.0)), 0.5);
            $frames = [];
            foreach ($times as $n => $t) {
                $f = "{$dir}/f".str_pad((string) $n, 2, '0', STR_PAD_LEFT).'.jpg';
                (new Process(['ffmpeg', '-nostdin', '-y', '-v', 'error', '-ss', (string) $t, '-i', $clip, '-frames:v', '1', '-vf', 'scale=640:-2', '-q:v', '4', $f]))->run();
                if (is_file($f)) {
                    $frames[$n] = $f;
                }
            }
            if (count($frames) < 2) {
                return 0.0;
            }
            $list = implode("\n", array_map(fn ($n, $f) => "Frame {$n} (".($times[$n])." s): {$f}", array_keys($frames), $frames));
            $prompt = <<<PROMPT
            These are frames, every half second, from the start of a video where a room is being modernised (new floor,
            furniture and décor appearing) while the camera moves. Frame 0 is the room as it really is.
            {$list}

            Open every image. Which is the first frame where anything new is visible compared with frame 0 — a piece of
            furniture, a rug, the floor starting to change, décor, a lamp? Ignore the camera's own movement.
            Return ONLY JSON: {"first_changed_frame": n} — or {"first_changed_frame": null} if nothing changes in these frames.
            PROMPT;
            $out = (string) ($this->runClaude($prompt, null, ['images' => array_values($frames), 'maxTurns' => count($frames) + 3, 'timeout' => 300])['result'] ?? '');
            $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;
            $n = $json['first_changed_frame'] ?? null;
            if ($n === null) {
                return round(end($times), 2);   // nothing yet in the first 6 s: hurry all of it
            }

            // The change begins between the last unchanged frame and this one.
            return isset($times[(int) $n]) ? max(0.0, round($times[(int) $n] - 0.5, 2)) : 0.0;
        } catch (\Throwable $e) {
            Log::warning('Change start failed: '.$e->getMessage());

            return 0.0;
        } finally {
            array_map('unlink', glob("{$dir}/*") ?: []);
            @rmdir($dir);
        }
    }

    /** Structural similarity of two images (0–1), ignoring colour and resolution. */
    public static function alignment(string $a, string $b): float
    {
        $p = new Process(['ffmpeg', '-nostdin', '-i', $a, '-i', $b, '-filter_complex', '[0]scale=320:180,format=gray[a];[1]scale=320:180,format=gray[b];[a][b]ssim', '-f', 'null', '-']);
        $p->setTimeout(60)->run();

        return preg_match('/All:([0-9.]+)/', $p->getErrorOutput(), $m) ? round((float) $m[1], 3) : 0.0;
    }
}
