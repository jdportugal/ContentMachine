<?php

namespace App\Jobs;

use App\Services\Aggregation\NewsAggregator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * Runs the aggregation (yt-dlp) OUTSIDE the web request: it is dozens of
 * subprocess calls that blow the max_execution_time if they run in the request. The
 * summary is cached; the News page reads it by polling (wire:poll).
 */
class AgregarConteudoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    /**
     * @param  array<int,string>|null  $plataformas
     */
    public function __construct(
        public readonly string $token,
        public readonly ?array $plataformas = null,
        public readonly ?int $limite = null,
    ) {}

    public function handle(NewsAggregator $aggregator): void
    {
        $resumo = $aggregator->aggregate($this->plataformas, $this->limite);

        Cache::put(self::key($this->token), $resumo, now()->addMinutes(30));
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true], now()->addMinutes(30));
    }

    public static function key(string $token): string
    {
        return 'noticias.agregacao.'.$token;
    }
}
