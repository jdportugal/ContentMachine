<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\Store\EffectStore;
use App\Services\RealEstate\ListingScraper;
use App\Services\RealEstate\ListingScriptWriter;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A voiceover for a property reel that's already cut: the listing (scraped from its
 * link, like ImportListingJob) and/or the house details the user typed → the pt-PT script (sized to the reel) → the narrator's takes. Picking a
 * take (escolherTake) re-cuts the reel with it; the animations are reused.
 */
class NarratePropertyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public string $clipId, public string $info, public string $url = '')
    {
        $this->captureProject();
    }

    public function handle(ListingScriptWriter $writer, ElevenLabsVoiceoverService $tts, ListingScraper $scraper, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->clipId);
        $disk = Storage::disk(config('contentmachine.clips.disk'));

        try {
            $listing = $p->meta['listing'] ?? [];
            $typed = self::facts($this->info);
            $scraped = $this->url !== '' ? $scraper->scrape($this->url) : null;
            // What the user typed wins over the listing; the agent typed on the form too.
            $facts = [
                'title' => $typed['title'] ?: (string) ($scraped['title'] ?? ''),
                'price' => $typed['price'] ?? $scraped['price'] ?? null,
                'details' => array_merge($scraped['details'] ?? [], $typed['details']),
            ];
            if ($scraped && empty($listing['agent']) && $scraped['agent']['name'] !== '') {
                $listing = array_merge($listing, ['agent' => $scraped['agent']['name'], 'phone' => $scraped['agent']['phone'], 'agency' => $scraped['agency']]);
                $this->agentPhoto($p, $scraped['agent']['photo'], $scraped['agent']['name'], $scraped['agency']);
            }
            if (empty($listing['licence']) && ! empty($scraped['ami'])) {
                $listing['licence'] = $scraped['ami'];
            }
            // Spoken over the whole reel, ending as the agent's card plays.
            $seconds = $p->output_path && is_file($p->output_path) ? PropertyVideoAnalyzer::probe($p->output_path)['duration'] : 35.0;
            $script = $writer->write([
                'title' => $facts['title'] ?: (string) ($listing['title'] ?? $p->title),
                'description' => trim(($scraped['description'] ?? '')."\n\n".$this->info),
                'details' => $facts['details'],
                'price' => $facts['price'],
                'ami' => (string) ($listing['licence'] ?? ''),
                'agent' => ['name' => (string) ($listing['agent'] ?? '')],
                'agency' => (string) ($listing['agency'] ?? 'RE/MAX'),
            ], $seconds);
            // An after-intro video (this clip's or the project's) comes with a line, said right after the intro.
            $effects = app(EffectStore::class);
            if (! empty($p->meta['after_intro']) || $effects->afterIntroPath()) {
                $script['voiceover'] = ListingScriptWriter::withAfterIntroLine($script['voiceover'], $effects->afterIntroLineFor($p->meta ?? []));
            }

            $voice = config('contentmachine.real_estate.voices.'.(($listing['gender'] ?? $script['agent_gender']) === 'f' ? 'f' : 'm'));
            $takes = [];
            for ($i = 0; $i < max(1, (int) ($voice['takes'] ?? 1)); $i++) {
                $takes[] = $audio = 'clips/uploads/'.Str::random(40).'.mp3';
                $tts->synthesizeWith(ListingScriptWriter::pronunciaFalada(ListingScriptWriter::tipologiaFalada($script['voiceover'])), $disk->path($audio), $voice['voice_id'], $voice['model'], $voice['stability'] ?? null);
            }

            $p = $store->findOrFail($this->clipId);
            $p->update([
                'status' => ClipRecord::STATUS_CHOOSING_VOICE,
                'meta' => array_merge($p->meta, [
                    'voice_takes' => $takes,
                    'step' => null,
                    'listing' => array_merge($listing, array_filter([
                        'voiceover' => $script['voiceover'],
                        'title' => $facts['title'],
                        'price' => $facts['price'],
                        'details' => $facts['details'],
                        'info' => $this->info,
                        'url' => $this->url,
                    ], fn ($v) => $v !== null && $v !== '' && $v !== [])),
                ]),
            ]);
        } catch (\Throwable $e) {
            $p = $store->findOrFail($this->clipId);
            // The reel is still there: back to it, with the reason.
            $p->update(['status' => ClipRecord::STATUS_DONE, 'meta' => array_merge($p->meta, ['step' => null,
                'listing' => array_merge($p->meta['listing'] ?? [], ['flags' => array_merge($p->meta['listing']['flags'] ?? [], ['The voiceover failed: '.Str::limit($e->getMessage(), 200)])])])]);

            throw $e;
        }
    }

    /** The listing's agent photo for the outro card, when the clip has none yet. */
    private function agentPhoto(ClipRecord $p, ?string $url, string $name, string $agency): void
    {
        $has = collect($p->images ?? [])->contains(fn ($img) => str_starts_with((string) ($img['description'] ?? ''), 'Retrato'));
        if ($has || ! $url) {
            return;
        }
        try {
            $bytes = Http::timeout(60)->get($url)->throw()->body();
            $path = 'clips/uploads/'.Str::random(40).'.jpg';
            Storage::disk(config('contentmachine.clips.disk'))->put($path, $bytes);
            $p->update(['images' => array_merge($p->images ?? [], [['id' => 'img_'.substr(md5($path), 0, 8), 'path' => $path, 'name' => $name.'.jpg', 'description' => "Retrato do consultor {$name}, da {$agency}."]])]);
        } catch (\Throwable) {
            // No photo: the outro keeps its placeholder; one can be added on the card.
        }
    }

    /**
     * "Label: value" lines → details; "Título"/"Title" and "Preço"/"Price" are pulled
     * out (the price as a whole number of euros). Free lines stay in the description.
     *
     * @return array{title:string,price:?int,details:array<string,string>}
     */
    public static function facts(string $info): array
    {
        $title = '';
        $price = null;
        $details = [];
        foreach (preg_split('/\R/', $info) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }
            [$k, $v] = array_map('trim', explode(':', $line, 2));
            if ($k === '' || $v === '') {
                continue;
            }
            $key = Str::lower(Str::ascii($k));
            if (in_array($key, ['titulo', 'title'], true)) {
                $title = $v;
            } elseif (in_array($key, ['preco', 'price'], true)) {
                $digits = preg_replace('/\D/', '', preg_replace('/[.,]\d{1,2}\s*€?$/', '', $v));
                $price = $digits !== '' ? (int) $digits : null;
            } else {
                $details[$k] = $v;
            }
        }

        return ['title' => $title, 'price' => $price, 'details' => $details];
    }
}
