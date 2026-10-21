<?php

namespace App\Services\RealEstate;

use App\Services\Clips\Api\RunsClaudeCli;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Reads a property walkthrough and decides the edit: an exterior opener and the
 * rooms an AI will modernise — for each, the approach to show and the simple
 * push/pull window the transformation plays over. The walk in between is
 * fast-forwarded by the editor.
 *
 * Claude sees the footage as numbered contact sheets (one tile per sampled
 * second), so a 2-minute walkthrough is ~10 images instead of 120.
 */
class PropertyVideoAnalyzer
{
    use RunsClaudeCli;

    private const PER_SHEET = 12; // tile=4x3

    private const MAX_SAMPLES = 240;

    protected function passo(): string
    {
        return 'clips_plano';
    }

    /**
     * Cut $seconds of $video from $start into $out, muted. Re-encoded (not
     * stream-copied) so the clip starts exactly on its first frame, not the
     * keyframe before.
     */
    public static function cut(string $video, float $start, float $seconds, string $out): void
    {
        (new Process(['ffmpeg', '-y', '-v', 'error', '-ss', (string) round($start, 3), '-i', $video, '-t', (string) round($seconds, 3),
            '-an', '-c:v', 'libx264', '-crf', '18', '-preset', 'veryfast', '-pix_fmt', 'yuv420p', '-movflags', '+faststart', $out]))
            ->setTimeout(300)->mustRun();
    }

    public static function fps(string $video): int
    {
        $p = new Process(['ffprobe', '-v', 'quiet', '-select_streams', 'v:0', '-show_entries', 'stream=r_frame_rate', '-of', 'csv=p=0', $video]);
        $p->run();
        [$num, $den] = array_map('floatval', explode('/', trim($p->getOutput()).'/1'));

        return $den > 0 ? (int) round($num / $den) : 30;
    }

    /** @return array{width:int,height:int,duration:float} */
    public static function probe(string $video): array
    {
        $p = new Process(['ffprobe', '-v', 'quiet', '-print_format', 'json', '-show_streams', '-show_format', '-select_streams', 'v:0', $video]);
        $p->run();
        $j = json_decode($p->getOutput(), true) ?: [];
        $s = $j['streams'][0] ?? [];
        [$w, $h] = [(int) ($s['width'] ?? 0), (int) ($s['height'] ?? 0)];
        // Phones record portrait as landscape + a rotation flag.
        $rot = abs((int) ($s['side_data_list'][0]['rotation'] ?? $s['tags']['rotate'] ?? 0));
        if ($rot === 90 || $rot === 270) {
            [$w, $h] = [$h, $w];
        }
        if ($w === 0 || $h === 0) {
            throw new RuntimeException('That file has no readable video stream.');
        }

        return ['width' => $w, 'height' => $h, 'duration' => (float) ($j['format']['duration'] ?? 0)];
    }

    /**
     * @return list<array{room:string,start:float,end:float,state:string,anim:?array{start:float,end:float,motion:string,mode:string,prompt:string}}>
     */
    public function analyze(string $video, string $workDir, int $maxRooms, string $context = ''): array
    {
        $info = self::probe($video);
        [$sheets, $step] = $this->sheets($video, $workDir, $info, 1.0);
        $list = collect($sheets)->values()->map(fn ($f, $i) => 'Sheet '.($i + 1).": {$f}")->implode("\n");
        $cuts = self::cuts($video);
        $cutList = $cuts === [] ? 'none — it is one continuous take' : implode(', ', array_map(fn ($c) => round($c, 2).' s', $cuts));
        $per = self::PER_SHEET;
        $dur = round($info['duration'], 1);

        $prompt = <<<PROMPT
        You are editing a real estate walkthrough video ({$dur} s, {$info['width']}x{$info['height']}) into a short reel.
        {$context}

        The footage is sampled every {$step} s into contact sheets of {$per} tiles (4 per row, 3 rows, read left→right, top→bottom).
        Tile n (1-based) of sheet k (1-based) is the frame at t = ((k-1)*{$per} + (n-1)) * {$step} seconds.
        Open and look at EVERY sheet before answering:
        {$list}

        Hard cuts (a new shot starts) at: {$cutList}. A kept stretch must lie inside ONE shot — never across a cut.

        Build the reel around the rooms an AI will MODERNISE: each chosen room is shown as the camera approaches it, then
        transforms on screen into a much more modern version of itself; the walk between rooms is fast-forwarded.
        1. Opener: the best stretch of the exterior/façade/street (2.5–4 s, smooth, no people), if the footage has one,
           as the first entry with "anim": null. No other entry may have "anim": null.
        2. Up to {$maxRooms} rooms to modernise, in walking order, choosing the house's main rooms first: living room,
           kitchen, main bedroom, dining room, then other bedrooms; a bathroom or terrace only if it's a wide, clean shot.
           For each:
           - start/end: the room's stretch — a 1.5–3 s slow push with the camera already INSIDE the room (never walking
             through a corridor, doorway or another room to get there), then the animation window; end = the window's end;
           - anim.start/anim.end: a window where the camera does ONE simple straight push forward or pull back (no pan,
             tilt, turn, orbit) for 2–5 s, entirely inside one shot, no people in it;
           - its FIRST frame must already show the room clearly — not a door edge, a doorway being walked through, or blur;
           - the window's LAST frame must be a wide, level view of the room (most of the floor and at least two walls in
             shot) — never a close-up of furniture, a corner or the floor: the image model re-frames close-ups;
           - prompt: in English, the modernised room as it should look in that LAST frame. Describe it (not instructions),
             proportional to the room, specific rather than generic ("modern" alone gets dated grey): fresh warm-white
             walls, warm light-oak or the existing floor refinished, slim modern ceiling lights, contemporary furniture
             suited to the room (name the pieces and where they stand), soft natural textures, a few plants and artwork;
             kitchens: handleless flat-front cabinets, stone worktop, integrated appliances in the same places;
             bathrooms: large-format tiles, floating vanity, frameless glass. Look at where the doors, doorways, windows
             and stairs are in that frame and name them as staying exactly where they are, the same size; nothing is
             placed in front of them and the walking paths stay clear. Keep the room's shape and the camera angle.

        Return ONLY JSON:
        {"rooms":[{"room":"Fachada","start":0.0,"end":0.0,"anim":null},
                  {"room":"Sala","start":0.0,"end":0.0,"anim":{"start":0.0,"end":0.0,"motion":"forward|backward","prompt":"…"}}]}
        PROMPT;

        $result = (string) ($this->runClaude($prompt, null, ['images' => $sheets, 'maxTurns' => count($sheets) + 4, 'timeout' => 900])['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $result, $m) ? json_decode($m[0], true) : null;
        if (! is_array($json['rooms'] ?? null)) {
            throw new RuntimeException('The video analysis did not return rooms.');
        }

        return self::sanitize($json['rooms'], $info['duration'], $maxRooms, $cuts);
    }

    /**
     * A listing's video, read like its photos: every room or space it shows, each
     * with a short description and the stretch to cut — the camera already inside
     * the room, steady, inside one shot (no doorway walked through, no transition).
     *
     * @return list<array{room:string,description:string,start:float,end:float}>
     */
    public function roomClips(string $video, string $workDir, string $context = ''): array
    {
        $info = self::probe($video);
        // Half-second tiles on a typical 1–2 min listing video: a transition can't hide between two.
        [$sheets, $step] = $this->sheets($video, $workDir, $info, 0.5);
        $list = collect($sheets)->values()->map(fn ($f, $i) => 'Sheet '.($i + 1).": {$f}")->implode("\n");
        $cuts = self::cuts($video);
        $cutList = $cuts === [] ? 'none — it is one continuous take' : implode(', ', array_map(fn ($c) => round($c, 2).' s', $cuts));
        $per = self::PER_SHEET;
        $dur = round($info['duration'], 1);

        $prompt = <<<PROMPT
        This is a real estate video ({$dur} s, {$info['width']}x{$info['height']}) that will be cut into short clips, one
        per room, to use in a reel instead of the listing's photos.
        {$context}

        The footage is sampled every {$step} s into contact sheets of {$per} tiles (4 per row, 3 rows, read left→right, top→bottom).
        Tile n (1-based) of sheet k (1-based) is the frame at t = ((k-1)*{$per} + (n-1)) * {$step} seconds.
        Open and look at EVERY sheet before answering:
        {$list}

        Hard cuts (a new shot starts) at: {$cutList}. A clip must lie inside ONE shot — never across a cut.

        List every distinct room or space the video shows (exterior/façade, living room, kitchen, each bedroom,
        bathrooms, terrace, garden, pool, view…), in the order they appear. ALSO add clips for each feature the
        voiceover above highlights that the video shows (the sea view, the pool, the fireplace, the garden…), named
        after it ("Vista mar", "Piscina", "Lareira") — they play while that is said.
        The reel must not repeat footage, so use the video well: for each room or feature give up to 3 clips from
        DIFFERENT stretches (another angle, another part of the room), never overlapping, as long as each one is
        good — name them "Sala", "Sala 2", "Sala 3". The most important rooms and features deserve the most clips.
        The outside — the building, the street, the surroundings and every view — opens the reel and comes back for
        each line about the area or the town: give it up to 4 clips from different stretches ("Exterior", "Rua",
        "Vista mar 2"…), not just one.
        For each clip, a stretch of 4 to 10 s — as long as it stays clean; shorter only when that room or view has no
        longer clean stretch — where:
        - what it is named after is CLEARLY IN FRAME from its first tile to its last: a view clip shows the view
          itself (the sea, the landscape) filling a good part of the frame — never the camera panning or walking
          towards it, never the moment before it appears; a room clip has the camera already INSIDE that room —
          never walking through a doorway or corridor, never entering or leaving, never another room;
        - no transition of any kind: no cut, dissolve, fade, whip-pan, blur, black or white frame — start a tile AFTER
          one ends and stop a tile BEFORE the next begins;
        - the camera holds, moves straight, or pans slowly and steadily — never while it SPINS or TURNS quickly
          (the view swinging across the room from one tile to the next), never shaking; no people and no on-screen
          text, titles or logos.
        Every room or feature the voiceover mentions MUST get at least one clip — its steadiest stretch, even if not
        perfect. Skip only what the voiceover doesn't mention and has no clean stretch; never name a clip after
        something it doesn't show.
        "room": its name in European Portuguese ("Sala", "Cozinha", "Quarto principal", "Casa de banho", "Fachada",
        "Vista mar"…).
        "description": one sentence in European Portuguese saying what the clip shows (the room or view, its main
        features, light and finishes), like a photo caption — only what is visible in that stretch.

        Return ONLY JSON:
        {"rooms":[{"room":"Sala","description":"…","start":0.0,"end":0.0}]}
        PROMPT;

        $result = (string) ($this->runClaude($prompt, null, ['images' => $sheets, 'maxTurns' => count($sheets) + 4, 'timeout' => 900])['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $result, $m) ? json_decode($m[0], true) : null;
        $clips = self::sanitizeClips(is_array($json['rooms'] ?? null) ? $json['rooms'] : [], $info['duration'], $cuts);

        // The rules above are strict: a fast handheld walkthrough (an 18 s video, six
        // cuts, every shot through a doorway) came back with nothing and failed the
        // import. It is the property's own footage, so a rough clip beats none.
        return $this->refineClips($video, $workDir, $clips)
            ?: $clips
            ?: self::shotClips($info['duration'], $cuts);
    }

    /**
     * Last resort when no clean room was found: the video cut along its own shots,
     * each long shot in pieces of at most 6 s, anything under 1.5 s dropped.
     *
     * @param  list<float>  $cuts
     * @return list<array{room:string,description:string,start:float,end:float}>
     */
    public static function shotClips(float $duration, array $cuts): array
    {
        $bounds = array_merge([0.0], $cuts, [$duration]);
        $out = [];
        for ($b = 0; $b < count($bounds) - 1; $b++) {
            // ponytail: 0.2 s off each cut (half sanitizeClips' margin), so short shots survive.
            $start = $bounds[$b] + ($b > 0 ? 0.2 : 0.0);
            $end = $bounds[$b + 1] - ($b + 1 < count($bounds) - 1 ? 0.2 : 0.0);
            $pieces = (int) ceil(($end - $start) / 6.0);
            for ($i = 0; $i < $pieces; $i++) {
                $s = $start + $i * ($end - $start) / $pieces;
                $e = $start + ($i + 1) * ($end - $start) / $pieces;
                if ($e - $s >= 1.5) {
                    $out[] = ['room' => 'Imóvel', 'description' => 'Excerto do vídeo do imóvel.', 'start' => round($s, 2), 'end' => round($e, 2)];
                }
            }
        }

        return $out;
    }

    /**
     * The $n most striking clips for a music-only opening (the view, the façade,
     * the best rooms — what makes someone stop scrolling), strongest first, by their captions.
     * Falls back to the first $n when the answer is unusable.
     *
     * @param  list<array{id:string,description:string}>  $clips
     * @return list<string> their ids, in the order to show them
     */
    public function bestClips(array $clips, int $n): array
    {
        $ids = array_column($clips, 'id');
        $list = collect($clips)->map(fn ($c) => "- {$c['id']}: {$c['description']}")->implode("\n");
        $prompt = <<<PROMPT
        These are the video clips of a property, cut from its walkthrough. A real estate reel opens with a few of them,
        music only, cut on the beat. Pick the {$n} that make someone stop scrolling — the view, the façade or the
        surroundings, the most impressive rooms — strongest first (the opening takes the first two to five).
        Different spaces: never two clips of the same room.
        {$list}

        Return ONLY JSON: {"clips":["vid_…"]}
        PROMPT;
        try {
            $result = (string) ($this->runClaude($prompt, null, ['maxTurns' => 1, 'timeout' => 300])['result'] ?? '');
            $json = preg_match('/\{.*\}/s', $result, $m) ? json_decode($m[0], true) : null;
            $picked = array_values(array_intersect(array_unique((array) ($json['clips'] ?? [])), $ids));
        } catch (\Throwable) {
            $picked = [];
        }

        return array_slice(array_values(array_unique(array_merge($picked, $ids))), 0, $n);
    }

    /**
     * Second look, closer: each chosen stretch as a strip of frames every 0.5 s,
     * trimmed to the frames fully inside the room (a doorway or door jamb at the
     * start, a turn towards the exit at the end, are cut off).
     *
     * @param  list<array{room:string,description:string,start:float,end:float}>  $clips
     * @return list<array{room:string,description:string,start:float,end:float}>
     */
    private function refineClips(string $video, string $workDir, array $clips): array
    {
        if ($clips === []) {
            return [];
        }
        $lines = [];
        $strips = [];
        foreach ($clips as $i => $c) {
            $strips[] = $file = "{$workDir}/strip-".($i + 1).'.jpg';
            (new Process(['ffmpeg', '-y', '-v', 'error', '-ss', (string) $c['start'], '-i', $video, '-t', (string) round($c['end'] - $c['start'], 2),
                '-vf', 'fps=2,scale=320:-2,tile=4x5:padding=4:color=white', '-frames:v', '1', '-q:v', '4', $file]))->setTimeout(120)->run();
            $lines[] = 'Clip '.($i + 1)." — \"{$c['room']}\" ({$c['description']}): {$file}";
        }
        $list = implode("\n", $lines);

        $prompt = <<<PROMPT
        Each image is a strip of frames from one short clip of a real estate video, a frame every 0.5 s (tiles numbered
        from 1, 4 per row, read left→right, top→bottom; white tiles at the end are padding). Open every image:
        {$list}

        Each clip will be shown while the voiceover talks about what it is named after, so EVERY kept tile must show
        that clearly. For each clip give the first and the last tile that do — keep every tile that qualifies, trimming
        only the ones that fail (longer clips are better):
        - a room: the camera fully inside it — drop tiles where it is still in or passing a doorway (a door, door frame
          or jamb sliding across the edge of the frame, part of another room or a corridor visible);
        - a view or outdoor feature (sea, landscape, pool, garden): it is in frame and fills a good part of it — drop
          the tiles where the camera is still panning or walking towards it, or shows only walls, railings or rooftops;
        - drop tiles where the camera spins or turns quickly (the view swings across the room from one tile to the
          next — a slow, steady pan is fine), and at the end where it turns away, a transition starts (dissolve, fade,
          blur, black or white), a person appears, or the image shakes.
        "keep": false only when the clip never shows what it is named after. When fewer than 4 consecutive tiles are
        clean, keep the 4 steadiest consecutive ones that show it.
        "description": one sentence in European Portuguese saying what the KEPT tiles show — only what is visible.

        Return ONLY JSON: {"clips":[{"clip":1,"first":1,"last":12,"keep":true,"description":"…"}]}
        PROMPT;

        $result = (string) ($this->runClaude($prompt, null, ['images' => $strips, 'maxTurns' => count($strips) + 4, 'timeout' => 600])['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $result, $m) ? json_decode($m[0], true) : null;
        if (! is_array($json['clips'] ?? null)) {
            return $clips;   // the first pass already keeps to one shot; the refinement only tightens
        }

        return self::trimClips($clips, $json['clips']);
    }

    /**
     * Apply the tile picks: tile n (1-based) is t = start + (n-1)·0.5 s.
     *
     * @return list<array{room:string,description:string,start:float,end:float}>
     */
    public static function trimClips(array $clips, array $picks): array
    {
        $out = [];
        foreach ($clips as $i => $c) {
            $pick = collect($picks)->first(fn ($p) => (int) ($p['clip'] ?? 0) === $i + 1);
            if ($pick && ($pick['keep'] ?? true) === false) {
                continue;
            }
            if ($pick) {
                $c['description'] = trim((string) ($pick['description'] ?? '')) ?: $c['description'];
                $start = $c['start'] + (max(1, (int) ($pick['first'] ?? 1)) - 1) * 0.5;
                $c['end'] = round(min($c['end'], $c['start'] + max(1, (int) ($pick['last'] ?? 99)) * 0.5), 2);
                $c['start'] = round($start, 2);
            }
            if ($c['end'] - $c['start'] >= 2.0) {
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Keep each clip inside one shot, trimmed off the cut edges (a tile is a
     * sample, the transition may start between two), at least 2 s and at most 10 s.
     *
     * @return list<array{room:string,description:string,start:float,end:float}>
     */
    public static function sanitizeClips(array $rooms, float $duration, array $cuts = []): array
    {
        $bounds = array_merge([0.0], $cuts, [$duration]);
        $out = [];
        usort($rooms, fn ($a, $b) => (float) ($a['start'] ?? 0) <=> (float) ($b['start'] ?? 0));
        $taken = 0.0;   // clips never overlap: the same footage twice would read as a repeat
        foreach ($rooms as $r) {
            $start = max($taken, 0.0, (float) ($r['start'] ?? 0));
            $end = min((float) ($r['end'] ?? 0), $duration);
            for ($b = 0; $b < count($bounds) - 1; $b++) {
                $overlap = min($end, $bounds[$b + 1]) - max($start, $bounds[$b]);
                if ($overlap > 0 && (! isset($best) || $overlap > $best[2])) {
                    $best = [$bounds[$b], $bounds[$b + 1], $overlap];
                }
            }
            if (isset($best)) {
                // ponytail: a fixed 0.4 s margin off each cut, a soft transition longer than that still shows.
                $start = max($start, $best[0] + ($best[0] > 0 ? 0.4 : 0.0));
                $end = min($end, $best[1] - ($best[1] < $duration ? 0.4 : 0.0));
                unset($best);
            }
            $end = min($end, $start + 10.0);
            if ($end - $start < 2.0) {
                continue;
            }
            $taken = $end;
            $out[] = [
                'room' => trim((string) ($r['room'] ?? '')) ?: 'Divisão',
                'description' => trim((string) ($r['description'] ?? '')),
                'start' => round($start, 2),
                'end' => round($end, 2),
            ];
        }

        return $out;
    }

    /**
     * Contact sheets of the footage, one tile per $step seconds (at least
     * $minStep, at most MAX_SAMPLES tiles).
     *
     * @return array{0:list<string>,1:float}
     */
    private function sheets(string $video, string $workDir, array $info, float $minStep): array
    {
        $step = max($minStep, round($info['duration'] / self::MAX_SAMPLES, 2));
        $tileW = $info['width'] >= $info['height'] ? 480 : 270;

        // select: frames before t=0 (an edit list's pre-roll) would shift every tile
        // against the -ss timeline the cuts use.
        @mkdir($workDir, 0775, true);
        array_map('unlink', glob("$workDir/sheet-*.jpg") ?: []);
        $p = new Process(['ffmpeg', '-y', '-v', 'error', '-i', $video, '-vf', "select='gte(t,0)',fps=1/{$step},scale={$tileW}:-2,tile=4x3:padding=4:color=white", '-q:v', '4', "$workDir/sheet-%02d.jpg"]);
        $p->setTimeout(600)->run();
        $sheets = glob("$workDir/sheet-*.jpg") ?: [];
        if ($sheets === []) {
            throw new RuntimeException('Could not sample frames from the video: '.trim($p->getErrorOutput()));
        }

        return [$sheets, $step];
    }

    /**
     * For a section the user marked: what room it is, and the modernised room as
     * it should look in the section's last frame (the image edit's brief).
     *
     * @return array{room:string,prompt:string,cleanup:list<string>}
     */
    public function modernise(string $frame, string $note = ''): array
    {
        $wish = trim($note) !== '' ? "The owner's wish for this room: «{$note}» — follow it." : '';
        $prompt = <<<PROMPT
        This is a frame from a real estate walkthrough: {$frame}
        An image model will edit it so the room looks modern. {$wish}
        Many of these houses are mid-renovation. FIRST look hard at the photo for everything unfinished or messy, and
        list each one with where it is: loose objects (picture frames, boards, doors or mirrors leaning on walls, boxes,
        sacks, bags, cardboard, cans, bottles, rubbish), building materials and tools (ladders, buckets, paint tins,
        trestles, tile stacks, cement bags, scaffolding), exposed or dangling wires and cables, open junction boxes,
        bare bulbs hanging on a cable, missing switch or socket covers, raw/patched/unpainted plaster or concrete,
        holes, cracks, stains, damp marks, tape, missing skirting boards, protective plastic or sheeting, dust, rubble
        and paint splashes. Leave out what is fine.

        Then write the image model's instruction in English. When the list isn't empty it STARTS with a "Finish and
        clean" sentence naming each item and its finished result, e.g. "Finish and clean the room: remove the ladder,
        the paint buckets and the two picture frames leaning against the left wall; hide every loose wire inside the
        walls and replace the bare bulb with a modern pendant; skim and paint the raw plaster walls smooth warm white;
        add white skirting boards; sweep away all dust and rubble." Then, in exactly this style (a proven real estate
        staging prompt):

        Rooms you furnish (living, dining, bedroom, office, terrace):
          "Furnish [the room] with modern furniture, including [4–6 named pieces suited to the room, e.g. a sectional,
          rug, coffee table, light fixture and two accent chairs]. [One finishing touch, e.g. Add greenery to the
          shelves.] Replace the floor with modern wide-plank laminate in a warm light oak tone.
          [Where the main pieces stand, from what you see, e.g. Make the seating centred and facing the window.]
          Keep [each door, doorway, corridor opening and window you see, with where it is] clear and exactly as it is.
          Make everything proportional to the room. All the furniture should be modern and cozy in style."
        Kitchens and bathrooms you renovate instead:
          "Renovate the [kitchen] to be modern and minimal. Keep the exact same proportions of the room and keep every
          appliance and fixture in the same location and size, replaced with new modern versions. [Specific finishes,
          e.g. Change the cabinets to handleless warm-white cabinets and make the counter and backsplash light stone.]
          [Kitchens: Replace the floor with modern wide-plank laminate in a warm light oak tone. Bathrooms: Replace the
          floor with large-format warm light-grey stone-look tiles.]
          Keep [each door, doorway, corridor opening and window you see, with where it is] exactly as it is."

        Be specific (name colours and materials only where it matters; "modern" alone gets dated grey). Never move,
        cover, add or remove a door, doorway, corridor opening, window or staircase; nothing stands in front of one.
        Return ONLY JSON: {"room": "Sala", "cleanup": ["the ladder by the window", "…"], "prompt": "…"} — "room" is the
        room's name in European Portuguese; "cleanup" is your list (empty when the room is already finished and tidy).
        PROMPT;

        $out = (string) ($this->runClaude($prompt, null, ['images' => [$frame], 'maxTurns' => 4, 'timeout' => 300])['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $out, $m) ? json_decode($m[0], true) : null;
        $brief = trim((string) ($json['prompt'] ?? ''));
        if ($brief === '') {
            throw new RuntimeException('Could not write the modernisation brief for this section.');
        }

        return [
            'room' => trim((string) ($json['room'] ?? '')) ?: 'Divisão',
            'prompt' => $brief,
            'cleanup' => array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($json['cleanup'] ?? [])), 'strlen')),
        ];
    }

    /**
     * Where the footage hard-cuts to a new shot (ffmpeg scene score > 0.3), in
     * the same t ≥ 0 timeline the cuts use. One sample per second can't see a
     * cut, and a stretch across one ends on the wrong room.
     *
     * @return list<float>
     */
    public static function cuts(string $video): array
    {
        $p = new Process(['ffmpeg', '-v', 'info', '-i', $video, '-vf', "select='gte(t,0)',scale=320:-2,select='gt(scene,0.3)',showinfo", '-an', '-f', 'null', '-']);
        $p->setTimeout(1200)->run();
        preg_match_all('/pts_time:([0-9.]+)/', $p->getErrorOutput(), $m);

        return array_values(array_map('floatval', $m[1]));
    }

    /**
     * Clamp the model's numbers to the footage: in order, no overlaps, windows
     * inside their room and ending on its last kept frame (the animation is the
     * room's last beat — the footage after it would undo the staging).
     *
     * @return list<array<string,mixed>>
     */
    public static function sanitize(array $rooms, float $duration, int $maxRooms, array $cuts = []): array
    {
        $bounds = array_merge([0.0], $cuts, [$duration]);
        $out = [];
        $cursor = 0.0;
        $animated = 0;
        usort($rooms, fn ($a, $b) => ($a['start'] ?? 0) <=> ($b['start'] ?? 0));

        foreach ($rooms as $r) {
            $start = max($cursor, min((float) ($r['start'] ?? 0), $duration));
            $end = min((float) ($r['end'] ?? 0), $duration);
            // Keep it inside the one shot it overlaps most.
            $best = null;
            for ($b = 0; $b < count($bounds) - 1; $b++) {
                $overlap = min($end, $bounds[$b + 1]) - max($start, $bounds[$b]);
                if ($overlap > 0 && ($best === null || $overlap > $best[2])) {
                    $best = [$bounds[$b], $bounds[$b + 1], $overlap];
                }
            }
            if ($best) {
                // A few frames off each cut: the scene score fires on the first new frame.
                $start = max($start, $best[0] + 0.05);
                $end = min($end, $best[1] - 0.05);
            }
            if ($end - $start < 1.0) {
                continue;
            }
            $anim = is_array($r['anim'] ?? null) ? $r['anim'] : null;
            if ($anim && $animated < $maxRooms && trim((string) ($anim['prompt'] ?? '')) !== '') {
                $aStart = max($start, min((float) ($anim['start'] ?? $start), $end - 1.5));
                $anim = [
                    'start' => round($aStart, 2),
                    'end' => round($end, 2),
                    'motion' => ($anim['motion'] ?? '') === 'backward' ? 'backward' : 'forward',
                    'mode' => 'modernise',
                    'prompt' => trim((string) $anim['prompt']),
                    'enabled' => true,
                ];
                $animated++;
            } elseif ($out !== []) {
                continue;   // only the opener plays without a transformation
            } else {
                $anim = null;
            }
            $out[] = ['room' => trim((string) ($r['room'] ?? 'Room')) ?: 'Room', 'start' => round($start, 2), 'end' => round($end, 2), 'state' => (string) ($r['state'] ?? ''), 'anim' => $anim];
            $cursor = $end;
        }

        return $out;
    }

    /**
     * Said in every image edit: many of these houses are mid-renovation, and the
     * edit must look like a finished, move-in-ready home.
     */
    public const FINISHED = 'Show the room as a finished, spotless, move-in-ready home: remove every loose object, tool, ladder, bucket, '
        .'paint tin, box, sack, piece of rubbish and building material, and every picture frame, board or panel leaning against a wall; '
        .'no exposed, dangling or loose wires or cables anywhere (all wiring hidden inside the walls), no open junction boxes, bare '
        .'bulbs or missing switch and socket covers — a proper modern light fitting instead; every wall and the ceiling fully '
        .'plastered, smooth and freshly painted, with no raw plaster, concrete, patches, holes, cracks, stains or tape; skirting '
        .'boards in place; no dust, rubble, paint splashes or protective sheeting. ';

    /**
     * Kling's prompt: describe the scene, don't instruct (Joel Van Beek's rule).
     * A calm, believable transformation rather than furniture flying in.
     */
    public static function videoPrompt(array $anim): string
    {
        $camera = $anim['motion'] === 'backward' ? 'backward' : 'forward';

        return (! empty($anim['cleanup']) ? 'The mess and unfinished work melt away first — the loose objects, tools, debris, dust and wires vanish and the raw walls become smooth and freshly painted. ' : '')
            .'The room modernises itself: the old floor is replaced by new warm light-oak laminate, and each new piece of modern '
            .'furniture and décor appears and settles gently into place one by one, smoothly and naturally. '
            .'The change starts right away in the first second and is complete before the last second. '
            .'Everything else is completely static and frozen exactly as in the first frame — the walls, ceiling, windows and every '
            .'opening'.(! empty($anim['keep']) ? ' ('.implode('; ', $anim['keep']).')' : '').': nothing in them opens, closes, swings or '
            .'appears, and no new opening, door or window appears in any wall. '
            ."The camera has super smooth {$camera} motion, as if shot on a gimbal; no shakes or bumps. Photorealistic, natural daylight. "
            .'Sound: only the things appearing — a soft airy whoosh as each piece arrives, a gentle thud as furniture settles, '
            .'a light fabric rustle, the click of new floorboards locking into place; quiet room tone between. No music, no voices.';
    }

    /**
     * The image edit for the end frame: an edit of this exact photo, never a
     * re-take, with the room's openings named as untouchable.
     *
     * @param  list<string>  $keep  from RoomStager::inventory()
     */
    public static function stillPrompt(array $anim, array $keep = []): string
    {
        // The floor is the change models skip most: say it first, whole-floor.
        $floor = stripos($anim['prompt'], 'laminate') !== false
            ? 'Replace the entire existing floor, edge to edge, with new modern wide-plank laminate in a warm light oak tone — none of the old floor remains. '
            : '';

        return 'Edit this exact photo; do not re-take it. Keep the exact same camera position, height, tilt, lens and crop: '
            .'every edge of the frame shows the same things and every line stays where it is. '
            .self::FINISHED
            .(! empty($anim['cleanup']) ? 'In particular: '.implode('; ', $anim['cleanup']).' — all gone or finished. ' : '')
            .$floor
            .rtrim($anim['prompt'], '. ').'. '
            .($keep !== [] ? 'These stay exactly as they are — same place, same size, same kind of thing, nothing in front of them: '.implode('; ', $keep).'. ' : '')
            .'Do not add, remove, close off or move any door, doorway, corridor opening, window or staircase, and do not turn one into another. '
            .'Photorealistic, natural daylight.';
    }

    /** The kie aspect ratio closest to the footage. */
    public static function aspect(int $w, int $h): string
    {
        $ratios = ['1:1' => 1, '2:3' => 2 / 3, '3:2' => 1.5, '3:4' => 0.75, '4:3' => 4 / 3, '4:5' => 0.8, '5:4' => 1.25, '9:16' => 9 / 16, '16:9' => 16 / 9, '21:9' => 21 / 9];
        $r = $w / max(1, $h);

        return collect($ratios)->sortBy(fn ($v) => abs(log($v / $r)))->keys()->first();
    }
}
