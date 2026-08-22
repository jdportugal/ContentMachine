<?php

namespace App\Jobs;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Aggregation\NewsAggregator;
use App\Services\Projects\ProjectContext;
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
    use RunsInProject;
    use SerializesModels;

    public int $timeout = 900;

    /**
     * @param  array<int,string>|null  $plataformas
     */
    public function __construct(
        public readonly string $token,
        public readonly ?array $plataformas = null,
        public readonly ?int $limite = null,
        public readonly int $diasAtras = 0,
    ) {
        $this->captureProject();
    }

    public function handle(NewsAggregator $aggregator): void
    {
        // The worker has no session: without this the run would read the DEFAULT
        // project's channels/settings and write to its vault, in its language.
        $this->activateProject();

        try {
            $resumo = $aggregator->aggregate($this->plataformas, $this->limite, $this->diasAtras);

            Cache::put(self::key($this->token), $resumo, now()->addMinutes(30));
        } finally {
            $this->libertar();
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true], now()->addMinutes(30));
        $this->libertar();
    }

    public static function key(string $token): string
    {
        return 'noticias.agregacao.'.$token;
    }

    /**
     * Takes the single aggregation slot of the active project. Returns false when
     * one is already running — the collection is dozens of yt-dlp subprocesses
     * writing the same vault, so two at once fight over it. Shared (cache) rather
     * than component state so the guard also holds across tabs and page reloads.
     */
    public static function reservar(string $token): bool
    {
        // TTL is the fallback for a worker killed hard enough to skip failed().
        return Cache::add(self::chaveEmCurso(self::slugAtivo()), $token, now()->addMinutes(20));
    }

    /** Token of the aggregation running for the active project, if any. */
    public static function emCurso(): ?string
    {
        return Cache::get(self::chaveEmCurso(self::slugAtivo()));
    }

    private function libertar(): void
    {
        Cache::forget(self::chaveEmCurso($this->projectSlug));
    }

    private static function slugAtivo(): string
    {
        return app(ProjectContext::class)->current()->slug;
    }

    private static function chaveEmCurso(string $slug): string
    {
        return 'noticias.agregacao.em-curso.'.$slug;
    }
}
