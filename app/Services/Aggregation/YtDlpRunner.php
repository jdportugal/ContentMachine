<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Real runner implementation: invokes yt-dlp in METADATA-ONLY mode
 * (never downloads media — only JSON and text subtitles) and makes HTTP GETs.
 *
 * The base command is configurable (config `contentmachine.aggregation.ytdlp_cmd`)
 * to support both `yt-dlp` (Docker) and `python3 -m yt_dlp` (local).
 */
class YtDlpRunner implements YtDlpRunnerContract
{
    /** @var array<int,string> */
    private array $base;

    private array $extractorArgs;

    private int $timeout;

    private ?string $lastError = null;

    public function __construct()
    {
        $cmd = (string) config('contentmachine.aggregation.ytdlp_cmd', 'yt-dlp');
        $this->base = array_values(array_filter(preg_split('/\s+/', trim($cmd)) ?: ['yt-dlp']));
        $this->extractorArgs = (array) config('contentmachine.aggregation.extractor_args', []);
        $this->timeout = (int) config('contentmachine.aggregation.timeout', 120);
    }

    public function listing(string $channelUrl, int $limit): array
    {
        $json = $this->runJson([
            '-J', '--flat-playlist', '--playlist-end', (string) max(1, $limit),
            ...$this->platformExtractorArgs($channelUrl),
            $channelUrl,
        ]);

        return $json ?? [];
    }

    public function metadata(string $url): array
    {
        $json = $this->runJson([
            '-J', '--skip-download',
            ...$this->platformExtractorArgs($url),
            $url,
        ]);

        return $json ?? [];
    }

    /** The most recent yt-dlp error during this runner's lifetime, if any. */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    public function fetch(string $url): ?string
    {
        try {
            $resposta = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0'])
                ->get($url);

            return $resposta->successful() ? $resposta->body() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int,string>  $args
     * @return array<string,mixed>|null
     */
    private function runJson(array $args): ?array
    {
        $resultado = Process::timeout($this->timeout)->run([...$this->base, ...$args]);

        if (! $resultado->successful()) {
            // Keep the real reason instead of swallowing it — YouTube failures on a
            // datacenter IP ("Sign in to confirm you're not a bot", extractor
            // breakage, stale yt-dlp) are otherwise invisible.
            $err = trim($resultado->errorOutput()) ?: trim($resultado->output());
            $this->lastError = $err !== '' ? Str::of($err)->squish()->limit(280)->toString() : 'yt-dlp exited with a non-zero status';
            Log::warning('yt-dlp failed', ['args' => $args, 'error' => $this->lastError]);

            return null;
        }

        $saida = trim($resultado->output());

        if ($saida === '' || $saida === 'null') {
            return null;
        }

        $decoded = json_decode($saida, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Platform-specific extractor arguments (e.g. force YouTube's `android`
     * client, which avoids the "The page needs to be reloaded" error).
     *
     * @return array<int,string>
     */
    private function platformExtractorArgs(string $url): array
    {
        foreach ($this->extractorArgs as $needle => $arg) {
            if (str_contains($url, (string) $needle)) {
                return ['--extractor-args', (string) $arg];
            }
        }

        return [];
    }
}
