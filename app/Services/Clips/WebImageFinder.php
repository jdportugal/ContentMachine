<?php

namespace App\Services\Clips;

use App\Services\Capture\SiteResolver;
use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Costs\CostLedger;
use App\Services\Monitoring\ApifyClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Finds REAL images on the web for the planner's image suggestions.
 *
 * The generator invents a picture; this one goes and gets the actual thing —
 * the person, the logo, the product, the page — which is exactly what the user
 * used to hunt down by hand. Two passes: the model searches the web (Pass A),
 * and whatever it could not name goes to an image-search actor (Pass B). A
 * suggestion nothing is found for simply stays pending and is generated as
 * before, so this never makes a clip worse than it was.
 */
class WebImageFinder
{
    use RunsClaudeCli;

    /** Some CDNs serve a 403 to anything that does not look like a browser. */
    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36';

    /** Below this it is a tracking pixel or a placeholder, not a picture. */
    private const MIN_BYTES = 10000;

    public function __construct(
        private readonly SiteResolver $resolver,
        private readonly ApifyClient $apify,
    ) {}

    /**
     * What to fetch for each suggestion. Keys with no confident answer are absent.
     *
     * @param  array<int,array<string,mixed>>  $requests
     * @return array<string,array{image:string,page:string,site:string}>
     */
    public function find(array $requests, string $title = '', string $summary = ''): array
    {
        if ($requests === []) {
            return [];
        }

        $found = $this->pesquisar($requests, $title, $summary);

        $faltam = array_values(array_filter(
            $requests,
            fn (array $r) => ($found[$r['key']]['image'] ?? '') === '' && ($found[$r['key']]['site'] ?? '') === '',
        ));
        foreach ($this->viaApify($faltam) as $key => $url) {
            $found[$key] = ['image' => $url, 'page' => $url, 'site' => ''];
        }

        return $found;
    }

    /**
     * Pass A — the model searches the web and names, per suggestion, the image
     * worth using. It answers in JSON because the API path keeps only text
     * blocks: a URL left inside a tool call would never reach us.
     *
     * @param  array<int,array<string,mixed>>  $requests
     * @return array<string,array{image:string,page:string,site:string}>
     */
    private function pesquisar(array $requests, string $title, string $summary): array
    {
        $needed = collect($requests)
            ->map(function (array $r) {
                $label = trim((string) ($r['label'] ?? ''));
                $prompt = trim((string) ($r['prompt'] ?? ''));

                return "- key {$r['key']}: ".($label !== '' ? $label.($prompt !== '' && $prompt !== $label ? " [wanted: {$prompt}]" : '') : $prompt);
            })
            ->implode("\n");

        $contexto = trim("The video is about: {$title}\n".Str::limit($summary, 600, ''));

        $prompt = <<<PROMPT
        You search the WEB for real images a short video needs. Search before you
        answer — never rely on memory for a URL.

        {$contexto}

        For each suggestion below, decide FIRST whether a real image genuinely
        beats an illustration. It does for anything that actually exists and is
        recognisable: a named person, company, brand, logo, product, building,
        place, event, chart from a real report, or the interface of an app or
        website. It does NOT for abstract ideas, metaphors, moods or invented
        scenes — those are better drawn, so leave them out entirely.

        Then answer with ONE of:
        - "image": a DIRECT link to the picture file (ends in .jpg/.png/.webp).
          Prefer sources that allow reuse — Wikimedia Commons, official press
          kits and newsrooms, official product pages — over stock-photo
          agencies. Put the page you found it on in "page".
        - "site": the https URL of a real page to SCREENSHOT, when the thing to
          show IS the page or app itself (a homepage, a docs page, a repo).

        SUGGESTIONS (key: what the scene shows):
        {$needed}

        Return ONLY JSON, no markdown:
        {"found": {"<key>": {"image": "<direct image URL or empty>", "page": "<source page URL>", "site": "<URL to screenshot or empty>", "why": "<one short line>"}}}

        Include a key ONLY when you are confident the image is really of that
        subject. When in doubt, omit it — a wrong photo of the wrong person is
        far worse than a generated illustration. {} if none qualify.
        PROMPT;

        try {
            $envelope = $this->runClaude($prompt, null, ['maxTurns' => 1, 'allowedTools' => 'WebSearch']);
        } catch (\Throwable $e) {
            Log::warning('Web image search failed: '.$e->getMessage());

            return [];
        }

        $found = $this->extractJson((string) ($envelope['result'] ?? ''))['found'] ?? [];
        if (! is_array($found)) {
            return [];
        }

        // The model can invent keys as easily as URLs — keep only what we asked.
        $validas = array_column($requests, 'key');
        $out = [];
        foreach ($found as $key => $dados) {
            if (! is_string($key) || ! is_array($dados) || ! in_array($key, $validas, true)) {
                continue;
            }
            $image = $this->url($dados['image'] ?? null);
            $site = $this->url($dados['site'] ?? null);
            if ($image === '' && $site === '') {
                continue;
            }
            $out[$key] = ['image' => $image, 'page' => $this->url($dados['page'] ?? null), 'site' => $site];
        }

        return $out;
    }

    /**
     * Pass B — an image-search actor for the suggestions the model could not
     * name. Nothing here is fatal: no token, a broken actor or an empty dataset
     * all mean the suggestion stays pending.
     *
     * ponytail: one actor run per suggestion — the mapping back to a key is then
     * trivial. Batch the queries into a single run if the latency ever matters.
     *
     * @param  array<int,array<string,mixed>>  $requests
     * @return array<string,string> key => image URL
     */
    private function viaApify(array $requests): array
    {
        $actor = (string) config('contentmachine.clips.image_search_actor', '');
        if ($requests === [] || $actor === '' || blank(config('services.apify.token'))) {
            return [];
        }

        $out = [];
        foreach ($requests as $r) {
            $query = trim((string) ($r['label'] ?? '')) ?: trim((string) ($r['prompt'] ?? ''));
            if ($query === '') {
                continue;
            }

            try {
                $rows = $this->apify->runActor($actor, [
                    'queries' => [$query],
                    'maxResultsPerQuery' => 3,
                ], 120);
            } catch (\Throwable $e) {
                // A bad token or actor fails identically for every query — stop.
                Log::warning('Apify image search failed: '.$e->getMessage());
                break;
            }

            $url = $this->primeiroUrl($rows);
            if ($url !== null) {
                $out[$r['key']] = $url;
                app(CostLedger::class)->registar('apify', (float) config('contentmachine.custos.apify_busca', 0.0), $query);
            }
        }

        return $out;
    }

    /**
     * The first usable image URL in an actor's dataset. Row shapes vary between
     * actors, so probe the names they commonly use.
     *
     * @param  array<int,array<string,mixed>>  $rows
     */
    private function primeiroUrl(array $rows): ?string
    {
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach (['imageUrl', 'originalImageUrl', 'contentUrl', 'image', 'url', 'thumbnailUrl', 'displayUrl'] as $campo) {
                $url = $this->url($row[$campo] ?? null);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        return null;
    }

    /**
     * Download an image and store it as a clip image, or null when it is not a
     * usable picture. Mirrors ClipImageGenerator's entry so the rest of the
     * pipeline cannot tell a fetched image from a generated one.
     *
     * @return array{id:string,path:string,description:string,tone:string,transparent:bool,fetched:bool,source:string}|null
     */
    public function fetch(string $url, string $label, string $source = ''): ?array
    {
        // Reuses the SSRF guard: this URL came from a model or a scraper, and it
        // must never reach the private network.
        $limpo = $this->resolver->normalizar($url);
        if ($limpo === null) {
            return null;
        }

        $bytes = $this->baixar($limpo);
        if ($bytes === null) {
            return null;
        }

        // One call that proves it is really an image, and gives size and format.
        $info = @getimagesizefromstring($bytes);
        if (! is_array($info)) {
            return null;
        }
        $min = (int) config('contentmachine.clips.fetch_min_width', 600);
        if (max((int) $info[0], (int) $info[1]) < $min) {
            return null; // a thumbnail looks awful fullscreen in a 1080x1920 frame
        }

        $ext = image_type_to_extension($info[2], false) ?: 'jpg';
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;

        try {
            $disk = Storage::disk(config('contentmachine.clips.disk'));
            $rel = 'clips/fetched/'.Str::lower(Str::random(12)).'.'.$ext;
            $disk->put($rel, $bytes);
            $abs = $disk->path($rel);

            return [
                'id' => 'web_'.substr(md5($rel), 0, 8),
                'path' => $rel,
                'description' => Str::limit(trim($label) !== '' ? $label : $limpo, 200),
                'tone' => ImageProbe::tone($abs),
                'transparent' => ImageProbe::hasAlpha($abs),
                'fetched' => true,
                'source' => $source !== '' ? $source : $limpo,
            ];
        } catch (\Throwable $e) {
            Log::warning('Storing a fetched image failed: '.$e->getMessage());

            return null;
        }
    }

    /** The bytes of an image URL, following a page's og:image once. */
    private function baixar(string $url, bool $seguirPagina = true): ?string
    {
        try {
            $r = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $r->successful()) {
            return null;
        }

        $tipo = strtolower((string) $r->header('Content-Type'));
        $body = (string) $r->body();

        // A page rather than an asset — the usual answer when a model names
        // where it SAW the image. Follow the page's own preview image once.
        if ($seguirPagina && str_contains($tipo, 'text/html')) {
            $og = $this->ogImage($body, $url);

            return $og === null ? null : $this->baixar($og, false);
        }

        if ($tipo !== '' && ! str_starts_with($tipo, 'image/')) {
            return null;
        }

        $tamanho = strlen($body);
        $max = (int) config('contentmachine.clips.fetch_max_bytes', 10485760);

        return $tamanho >= self::MIN_BYTES && $tamanho <= $max ? $body : null;
    }

    /** The page's declared preview image, made absolute. */
    private function ogImage(string $html, string $base): ?string
    {
        $cabeca = substr($html, 0, 100000);
        foreach (['og:image', 'twitter:image'] as $prop) {
            $p = preg_quote($prop, '/');
            foreach ([
                '/<meta[^>]+(?:property|name)=["\']'.$p.'["\'][^>]*content=["\']([^"\']+)["\']/i',
                '/<meta[^>]+content=["\']([^"\']+)["\'][^>]*(?:property|name)=["\']'.$p.'["\']/i',
            ] as $padrao) {
                if (preg_match($padrao, $cabeca, $m)) {
                    $abs = $this->absoluto(trim(html_entity_decode($m[1])), $base);
                    if ($abs !== null) {
                        return $abs;
                    }
                }
            }
        }

        return null;
    }

    private function absoluto(string $url, string $base): ?string
    {
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        $partes = parse_url($base);
        $scheme = $partes['scheme'] ?? 'https';
        $host = $partes['host'] ?? '';
        if ($host === '') {
            return null;
        }
        if (str_starts_with($url, '//')) {
            return $scheme.':'.$url;
        }

        return str_starts_with($url, '/') ? $scheme.'://'.$host.$url : null;
    }

    /** A plain http(s) URL, or '' — the model answers with prose and nulls alike. */
    private function url(mixed $valor): string
    {
        $texto = is_string($valor) ? trim($valor) : '';

        return $texto !== '' && preg_match('#^https?://#i', $texto) && filter_var($texto, FILTER_VALIDATE_URL) ? $texto : '';
    }

    /** Pull the first JSON object out of the model's reply. */
    private function extractJson(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) {
            $content = trim($m[1]);
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }

        return json_decode($content, true) ?: [];
    }
}
