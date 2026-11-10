<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\ImageProbe;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\Projects\ProjectRepository;
use App\Services\Projects\ProjectResources;
use App\Services\RealEstate\AgencyLogoFinder;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\Response;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Listing URL → a ready-to-plan animated clip: what used to be done by hand.
 * Scrapes the listing, writes the pt-PT voiceover, narrates it, collects the
 * photos (first one as "main"), the agent's portrait and the agency logo, then
 * hands the clip to the normal pipeline (TranscribeJob) with the listing set-up:
 * only reference images, no captions, upbeat corporate music.
 */
class ImportListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 1800;

    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    public function __construct(public string $clipId, public string $url)
    {
        $this->captureProject();
    }

    public function handle(ListingScraper $scraper, ListingScriptWriter $writer, ElevenLabsVoiceoverService $tts, AgencyLogoFinder $logos, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->clipId);

        try {
            $listing = $scraper->scrape($this->url);
            // The project's agent (one agent per project) stands in for the listing's.
            $resources = app(ProjectResources::class);
            $listing['agent'] = array_merge($listing['agent'], array_filter($resources->agent()));
            // The licence (AMI) on the end card: the one typed with the clip, else the project's, else the listing's — else none.
            $listing['ami'] = trim((string) ($p->meta['licence'] ?? '')) ?: ($listing['agent']['licence'] ?? '') ?: (string) ($listing['ami'] ?? '');
            $agent = $listing['agent'];
            $agency = $listing['agency'];

            $script = $writer->write($listing + ['notes' => (string) ($p->meta['script_notes'] ?? '')]);
            // An after-intro video (this listing's or the project's) comes with a line, said right after the intro.
            $effects = app(EffectStore::class);
            // A voiceover the customer wrote is said as written.
            $propria = trim((string) ($p->meta['narracao_propria'] ?? ''));
            if ($propria !== '') {
                $script['voiceover'] = $propria;
            } elseif (! empty($p->meta['after_intro']) || $effects->afterIntroPath()) {
                $script['voiceover'] = ListingScriptWriter::withAfterIntroLine($script['voiceover'], $effects->afterIntroLineFor($p->meta ?? []));
            }
            $p->update(['title' => $script['title'] ?: $listing['title']]);

            $flags = $script['flags'];
            if (! empty($p->meta['videos_swapped'])) {
                $flags[] = 'The property video and the after-intro were in each other\'s field — swapped.';
            }

            // A property walkthrough may bring its own voiceover, or none: then the
            // listing is only read for the agent's outro and the house facts.
            $property = $p->meta['property'] ?? null;
            $narrate = ! $property || ($property['narrate'] ?? true);

            // One narration, or several takes for the user to choose from by ear.
            $voice = config('contentmachine.real_estate.voices.'.$script['agent_gender']);
            $takes = [];
            for ($i = 0; $narrate && $i < max(1, (int) ($voice['takes'] ?? 1)); $i++) {
                $takes[] = $audio = 'clips/uploads/'.Str::random(40).'.mp3';
                $tts->synthesizeWith(ListingScriptWriter::pronunciaFalada(ListingScriptWriter::tipologiaFalada($script['voiceover'])), $this->disk()->path($audio), $voice['voice_id'], $voice['model'], $voice['stability'] ?? null);
            }
            $choose = count($takes) > 1;
            // A property walkthrough brings its own footage: no listing photos.
            // A listing video, when given, replaces the photos — the reel is its footage, cut per room —
            // unless the photos were asked to be mixed in.
            $video = $property ? null : ($p->meta['listing_video'] ?? null);
            $images = $property || ($video && empty($p->meta['mix_photos'])) ? [] : $this->photos($listing['photos']);
            $opening = null;
            if ($video) {
                $rooms = $this->roomClips($video, $listing['title'], $script['voiceover']);
                $images = array_merge($images, $rooms);
                // A beat-synced opening: the best clips, music only, before the voice (RenderJob::prependOpening).
                if (! empty($p->meta['beat_opening'])) {
                    // Ranked: the render takes the first three long enough for their turn.
                    $opening = ['clips' => app(PropertyVideoAnalyzer::class)->bestClips($rooms, 5)];
                }
            }
            // Photos the customer added that are not in the listing: part of the gallery like the listing's.
            $adicionadas = [];
            foreach ((array) ($p->meta['fotos_extra'] ?? []) as $n => $caminho) {
                if (is_string($caminho) && $this->disk()->exists($caminho)
                    && ($foto = $this->store($this->disk()->get($caminho), 'adicional-'.($n + 1), 'Foto adicional do imóvel, enviada pelo consultor.'))) {
                    $adicionadas[] = $foto;
                }
            }
            $images = array_merge($images, $adicionadas);
            if (! $property && $images === []) {
                throw new \RuntimeException('None of the listing photos could be downloaded.');
            }

            $who = 'Retrato '.($script['agent_gender'] === 'f' ? 'da consultora' : 'do consultor')." {$agent['name']}, da {$agency}.";
            // A photo uploaded with the URL wins over the project's, which wins over the listing's.
            $own = $p->meta['agent_photo'] ?? null;
            $projectPhoto = $resources->agentPhotoPath();
            $portrait = match (true) {
                $own && $this->disk()->exists($own) => $this->store($this->disk()->get($own), $agent['name'], $who),
                $projectPhoto !== null => $this->store((string) file_get_contents($projectPhoto), $agent['name'], $who),
                ! empty($agent['photo']) => $this->download($agent['photo'], $agent['name'], $who),
                default => null,
            };
            if ($portrait) {
                $images[] = $portrait;
            } else {
                $flags[] = 'Agent photo not found — add it by hand.';
            }

            $projectLogo = $resources->logoPath();
            $logo = $projectLogo ? $this->store((string) file_get_contents($projectLogo), 'logo', "Logótipo da {$agency}.") : $this->logo($logos, $agency);
            if ($logo) {
                $images[] = $logo;
            } else {
                $flags[] = "Logo for {$agency} not found — add it by hand.";
            }

            $extras = array_values(array_filter(array_map(
                fn ($img) => $this->store((string) file_get_contents($img['path']), pathinfo($img['file'], PATHINFO_FILENAME), $img['description']),
                $resources->images(),
            )));
            $images = array_merge($images, $extras);
            $extras = array_merge($adicionadas, $extras);   // named in the instructions so the planner uses them

            $p->update([
                'status' => $choose ? ClipRecord::STATUS_CHOOSING_VOICE : ($property ? ClipRecord::STATUS_ANALYZING : ClipRecord::STATUS_TRANSCRIBING),
                'source_path' => $choose || $property ? null : $takes[0],
                'images' => $images,
                'meta' => array_merge($p->meta ?? [], [
                    'background' => $this->background($store),
                    'lead' => '',
                    'instructions' => $this->instructions($listing, $script, $portrait, $logo, (bool) $property, $extras)
                        .($opening ? "\n\nThe reel is preceded by a music-only opening of ".implode(', ', $opening['clips']).' — use other footage for the scenes where you can.' : ''),
                    'fewer_images' => false,
                    'no_images' => false,
                    'only_reference_images' => true,
                    'auto_finish' => false,
                    'free_sfx' => false,
                    // The music the form chose (a property clip's, or the listing's), else the project's.
                    'musica' => isset($p->meta['musica']) ? $p->meta['musica'] : ($resources->music() ?: (string) config('contentmachine.real_estate.music')),
                    'musica_volume' => $property && isset($p->meta['musica_volume']) ? $p->meta['musica_volume'] : (float) config('contentmachine.real_estate.music_volume'),
                    'captions' => 'none',
                    'beat_opening' => $opening,
                    'voice_takes' => $choose ? $takes : null,
                    'property' => $property ? array_merge($property, $narrate ? ['voice' => $choose ? null : $takes[0]] : []) : null,
                    'listing' => [
                        'url' => $this->url,
                        'agent' => $agent['name'],
                        'phone' => $agent['phone'],
                        'email' => $agent['email'] ?? '',
                        'licence' => $listing['ami'],
                        'agency' => $agency,
                        'hook' => $script['hook'],
                        'voiceover' => $script['voiceover'],
                        'scenes' => $script['scenes'],
                        'flags' => $flags,
                        'message' => $script['message'],
                        'gender' => $script['agent_gender'],
                        // The house facts, for the property reel's 3D text.
                        'title' => $listing['title'],
                        'price' => $listing['price'],
                        'details' => $listing['details'],
                    ],
                ]),
            ]);

            if (! $choose) {
                $property ? AnalyzePropertyJob::dispatch($p->id) : TranscribeJob::dispatch($p->id);
            }
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Cut the listing video into one clip per room, each described like a photo.
     *
     * @return list<array<string,mixed>>
     */
    private function roomClips(string $video, string $title, string $voiceover = ''): array
    {
        $abs = $this->disk()->path($video);
        $work = storage_path('app/tmp/rooms-'.$this->clipId);
        try {
            $rooms = app(PropertyVideoAnalyzer::class)->roomClips($abs, $work, "The property: {$title}.\nThe voiceover the clips will play under: «{$voiceover}»");
        } finally {
            array_map('unlink', glob("$work/*") ?: []);
            @rmdir($work);
        }
        if ($rooms === []) {
            $secs = round(PropertyVideoAnalyzer::probe($abs)['duration']);
            throw new \RuntimeException("The property video ({$secs} s) shows no room or view that could be cut — is it the walkthrough of this property, not the after-intro or a logo?");
        }

        $clips = [];
        foreach ($rooms as $r) {
            $path = 'clips/uploads/'.Str::random(40).'.mp4';
            PropertyVideoAnalyzer::cut($abs, $r['start'], $r['end'] - $r['start'], $this->disk()->path($path));
            $secs = number_format($r['end'] - $r['start'], 1, ',', '');
            $clips[] = [
                'id' => 'vid_'.substr(md5($path), 0, 8),
                'path' => $path,
                'description' => trim("{$r['room']}: {$r['description']} (vídeo, {$secs} s)"),
                'name' => Str::slug($r['room']).'.mp4',
                'transparent' => false,
                'tone' => 'mixed',
                'video' => true,
                'source' => [$r['start'], $r['end']],   // where in the listing video (the editor's moment picker shows it)
            ];
        }

        return $clips;
    }

    private function disk()
    {
        return Storage::disk(config('contentmachine.clips.disk'));
    }

    /**
     * Every listing photo, in the listing's order; the first is named "main"
     * (the project's style manual opens on main.jpg).
     *
     * @param  list<string>  $urls
     * @return list<array<string,mixed>>
     */
    private function photos(array $urls): array
    {
        $responses = Http::pool(fn ($pool) => array_map(
            fn ($u) => $pool->timeout(60)->withHeaders(['User-Agent' => self::UA])->get($u),
            $urls,
        ));

        $images = [];
        foreach ($urls as $i => $url) {
            $r = $responses[$i] ?? null;
            if ($r instanceof Response && $r->successful()) {
                $entry = $this->store($r->body(), $i === 0 ? 'main' : pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME));
                if ($entry) {
                    $images[] = $entry;
                }
            }
        }

        return $images;
    }

    /** @return array<string,mixed>|null */
    private function download(string $url, string $name, string $description = ''): ?array
    {
        try {
            $r = Http::timeout(60)->withHeaders(['User-Agent' => self::UA])->get($url);
        } catch (\Throwable) {
            return null;
        }

        return $r->successful() ? $this->store($r->body(), $name, $description) : null;
    }

    /** Save image bytes as a clip upload entry named "<name>.<ext>", or null if they are not an image. */
    private function store(string $bytes, string $name, string $description = ''): ?array
    {
        $info = @getimagesizefromstring($bytes);
        if (! is_array($info)) {
            return null;
        }
        $ext = image_type_to_extension($info[2], false) ?: 'jpg';
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $path = 'clips/uploads/'.Str::random(40).'.'.$ext;
        $this->disk()->put($path, $bytes);
        $abs = $this->disk()->path($path);

        return [
            'id' => 'img_'.substr(md5($path), 0, 8),
            'path' => $path,
            'description' => $description,
            'name' => $name.'.'.$ext,
            'transparent' => ImageProbe::hasAlpha($abs),
            'tone' => ImageProbe::tone($abs),
            'video' => false,
        ];
    }

    /**
     * The agency logo: a per-office cache (clips/logos, shared by every project),
     * else the logo of an earlier clip for the same agency in any project, else
     * Google Images with Claude picking. The cached file is copied per clip:
     * editing a clip deletes the files it drops.
     */
    private function logo(AgencyLogoFinder $logos, string $agency): ?array
    {
        if ($agency === '') {
            return null;
        }
        $cacheBase = 'clips/logos/'.Str::slug($agency);
        $cached = collect(['png', 'jpg', 'webp', 'gif'])->map(fn ($e) => "{$cacheBase}.{$e}")->first(fn ($f) => $this->disk()->exists($f));

        if ($cached === null) {
            $bytes = ($past = $this->logoFromPastClips($agency)) ? $this->disk()->get($past) : $logos->find($agency);
            $info = $bytes ? @getimagesizefromstring($bytes) : false;
            if (! is_array($info)) {
                return null;
            }
            $ext = image_type_to_extension($info[2], false) ?: 'png';
            $cached = $cacheBase.'.'.($ext === 'jpeg' ? 'jpg' : $ext);
            $this->disk()->put($cached, $bytes);
        }

        return $this->store((string) $this->disk()->get($cached), 'logo', "Logótipo da {$agency}.");
    }

    /** Path of a logo.* image from an earlier clip, in any project, that names this agency (its description or its listing). */
    private function logoFromPastClips(string $agency): ?string
    {
        $needle = Str::lower(Str::ascii($agency));

        foreach (app(ProjectRepository::class)->all() as $project) {
            foreach (glob(rtrim($project->path, '/').'/clips-animados/*.json') ?: [] as $file) {
                $clip = json_decode((string) file_get_contents($file), true);
                $sameAgency = Str::lower(Str::ascii((string) ($clip['meta']['listing']['agency'] ?? ''))) === $needle;
                foreach ($clip['images'] ?? [] as $img) {
                    $isLogo = str_starts_with(Str::lower((string) ($img['name'] ?? '')), 'logo.');
                    $named = str_contains(Str::lower(Str::ascii((string) ($img['description'] ?? ''))), $needle);
                    if ($isLogo && ($sameAgency || $named) && ! empty($img['path']) && $this->disk()->exists($img['path'])) {
                        return $img['path'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * The backdrop this project's clips have been using (the newest clip that
     * named one, if it is still enabled), else let the planner choose.
     */
    private function background(ClipStore $store): string
    {
        $enabled = app(BackgroundStore::class)->enabled()->pluck('slug')->all();
        $last = $store->all()
            ->map(fn (ClipRecord $c) => $c->meta['background'] ?? null)
            ->first(fn ($bg) => is_string($bg) && in_array($bg, $enabled, true));

        return $last ?? 'auto';
    }

    private function instructions(array $listing, array $script, ?array $portrait, ?array $logo, bool $property = false, array $extras = []): string
    {
        $agent = $listing['agent'];
        $details = collect($listing['details'])->map(fn ($v, $k) => "{$k}\n{$v}")->implode("\n");
        // The walkthrough replaces the photo slideshow: no photo scenes to suggest.
        $scenes = collect($property ? [] : $script['scenes'])
            ->map(fn ($s) => '- '.($s['photo'] ?? '').' → «'.($s['caption'] ?? '').'»')
            ->implode("\n");

        $files = array_filter([
            $portrait ? "The agent's photo is {$portrait['name']}" : null,
            $logo ? "the agency logo is {$logo['name']}" : null,
        ]);
        $contacts = implode('', array_filter([
            ! empty($agent['email']) ? " Email: {$agent['email']}." : '',
            ! empty($listing['ami']) ? " Show the licence number ({$listing['ami']}) small on the outro card." : '',
        ]));
        $extraList = collect($extras)->map(fn ($e) => "- {$e['name']}: {$e['description']}")->implode("\n");

        return trim(
            "Make sure the intro information always includes the name of the agent - {$agent['name']} - and phone number - {$agent['phone']}. Agency: {$listing['agency']}.{$contacts}\n"
            .($files ? implode('; ', $files).".\n" : '')
            .($extraList !== '' ? "The agent's own images — use them where they fit:\n{$extraList}\n" : '')
            ."On-screen hook for the first frame: «{$script['hook']}».\n"
            .($property ? "The base is the property walkthrough video: keep it full screen the whole time. Overlay an intro card (agent name, phone, agency logo) over the first seconds and an outro card with the agent's name, phone and the agency logo over the last seconds. Keep overlays small and away from the centre so the furniture animations stay visible.\n" : '')
            .($scenes !== '' ? "\nSuggested scenes (photo → on-screen caption):\n{$scenes}\n" : '')
            ."\nHere are the house details:\n\n{$listing['title']}\n{$details}\n\nDescrição\n{$listing['description']}"
        );
    }
}
