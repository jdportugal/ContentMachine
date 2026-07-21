<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Implementação real do runner: invoca o yt-dlp em modo METADADOS-ONLY
 * (nunca descarrega média — apenas JSON e legendas de texto) e faz GETs HTTP.
 *
 * O comando base é configurável (config `contentmachine.aggregation.ytdlp_cmd`)
 * para suportar tanto `yt-dlp` (Docker) como `python3 -m yt_dlp` (local).
 */
class YtDlpRunner implements YtDlpRunnerContract
{
    /** @var array<int,string> */
    private array $base;

    private array $extractorArgs;

    private int $timeout;

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
     * Argumentos de extractor específicos por plataforma (ex.: forçar o cliente
     * `android` do YouTube, que evita o erro "The page needs to be reloaded").
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
