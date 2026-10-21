<?php

namespace App\Services\RealEstate;

use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Costs\CostLedger;
use App\Services\Monitoring\ApifyClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Finds an agency's logo on Google Images (via the Apify image-search actor)
 * and has Claude look at the candidates to pick the one that really is this
 * office's logo — not the generic RE/MAX balloon, not a sister office's.
 */
class AgencyLogoFinder
{
    use RunsClaudeCli;

    private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0 Safari/537.36';

    private const CANDIDATES = 8;

    public function __construct(private readonly ApifyClient $apify) {}

    /** The logo's image bytes, or null when nothing convincing turns up. */
    public function find(string $agency): ?string
    {
        $actor = (string) config('contentmachine.clips.image_search_actor', '');
        if ($actor === '' || blank(config('services.apify.token'))) {
            return null;
        }

        try {
            $rows = $this->apify->runActor($actor, ['queries' => ["{$agency} logo"], 'maxResultsPerQuery' => self::CANDIDATES], 180);
            app(CostLedger::class)->registar('apify', (float) config('contentmachine.custos.apify_busca', 0.0), "{$agency} logo");
        } catch (\Throwable $e) {
            Log::warning("Logo search for {$agency} failed: ".$e->getMessage());

            return null;
        }

        $dir = storage_path('app/tmp/logos-'.uniqid());
        @mkdir($dir, 0775, true);

        try {
            $files = [];
            foreach (array_slice($rows, 0, self::CANDIDATES) as $row) {
                // The full image when it is reachable (Facebook answers scripts with
                // HTML), else Google's own thumbnail of it.
                $bytes = $this->image((string) ($row['imageUrl'] ?? '')) ?? $this->image((string) ($row['thumbnailUrl'] ?? ''));
                if ($bytes !== null) {
                    // A real extension: the CLI's Read tool only opens files it recognises as images.
                    $ext = image_type_to_extension(getimagesizefromstring($bytes)[2], false) ?: 'jpg';
                    $file = $dir.'/'.(count($files) + 1).'.'.($ext === 'jpeg' ? 'jpg' : $ext);
                    file_put_contents($file, $bytes);
                    $files[count($files) + 1] = $file;
                }
            }

            $pick = $this->pick($agency, $files);

            return $pick !== null ? file_get_contents($files[$pick]) : null;
        } finally {
            array_map('unlink', glob($dir.'/*') ?: []);
            @rmdir($dir);
        }
    }

    /** Ask Claude which candidate is this office's logo. @param array<int,string> $files */
    private function pick(string $agency, array $files): ?int
    {
        if ($files === []) {
            return null;
        }
        $list = collect($files)->map(fn ($f, $n) => "{$n}: {$f}")->implode("\n");

        $prompt = <<<PROMPT
        These are Google Images results for the logo of the real estate office "{$agency}".
        Look at every image, then pick the ONE that is this office's own logo:
        - it must show the office's name ("{$agency}", or its group name, e.g. "Grupo …");
        - the generic RE/MAX balloon alone, another office's logo, a photo of a shop
          front, a person or a listing are all wrong;
        - prefer a clean logo on a plain background over one on a photo.

        Candidates (number: file):
        {$list}

        Return ONLY JSON: {"pick": <number>} — or {"pick": null} if none is clearly this office's logo.
        PROMPT;

        try {
            $result = (string) ($this->runClaude($prompt, null, ['images' => array_values($files), 'maxTurns' => count($files) + 3, 'timeout' => 300])['result'] ?? '');
        } catch (\Throwable $e) {
            Log::warning("Logo pick for {$agency} failed: ".$e->getMessage());

            return null;
        }

        $pick = preg_match('/\{.*\}/s', $result, $m) ? (json_decode($m[0], true)['pick'] ?? null) : null;

        return is_numeric($pick) && isset($files[(int) $pick]) ? (int) $pick : null;
    }

    private function image(string $url): ?string
    {
        if (! str_starts_with($url, 'https://')) {
            return null;
        }
        try {
            $r = Http::timeout(30)->withHeaders(['User-Agent' => self::UA])->get($url);
        } catch (\Throwable) {
            return null;
        }
        $info = $r->successful() ? @getimagesizefromstring($r->body()) : false;

        return is_array($info) && max($info[0], $info[1]) >= 200 ? $r->body() : null;
    }
}
