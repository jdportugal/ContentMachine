<?php

namespace App\Jobs;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Publicacoes\Rendering\KieProgress;
use App\Services\Publicacoes\Rendering\SlideRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Draws the cards of a piece outside the web request cycle.
 *
 * The SVG renderer is instantaneous, but kie.ai (nano-banana-pro) submits and
 * polls one task per card — too slow for a web request. It runs here,
 * in a queue, like AdsMaker's GeneratePostImagesJob. The images go
 * in public/media/publicacoes/{token}/ and the paths are cached for the
 * workshop to read them by polling.
 */
class GerarImagensJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 900;

    /**
     * Retry the piece a few times: with the per-card KieProgress store, a retry
     * resumes (reuses finished cards, re-polls in-flight ones) instead of
     * regenerating the whole carousel — so retrying only fetches the failed card.
     */
    public int $tries = 3;

    public function backoff(): int
    {
        return 15;
    }

    /**
     * @param  array<int,array{titulo?:string,texto?:string}>  $slides
     * @param  array<int,string>  $referencias  global references (applied to all cards)
     * @param  array<int,string>  $prompts  kie prompt per card (edited in the workshop; '' = compose)
     * @param  array<int,array<int,string>>  $anexos  paths of attached images per card
     * @param  array<int,array<int,string>>  $anexosDescr  descriptions of the attached images per card
     */
    public function __construct(
        public string $tipo,
        public string $titulo,
        public string $plataforma,
        public string $legenda,
        public array $slides,
        public string $token,
        public string $proporcao = '',
        public array $referencias = [],
        public string $notaSlug = '', // if set, persists the images on the note
        public array $prompts = [],
        public array $anexos = [],
        public array $anexosDescr = [],
    ) {}

    public function handle(SlideRenderer $renderer, PublicacaoKinds $kinds): void
    {
        try {
            $kind = $kinds->get($this->tipo) ?? [];
            if ($this->proporcao !== '') {
                $kind['proporcao'] = $this->proporcao;
            }
            $kind['_refs'] = $this->referencias;
            $kind['_prompts'] = $this->prompts;
            $kind['_anexos'] = $this->anexos;
            $kind['_anexosDescr'] = $this->anexosDescr;
            // Per-card progress (keyed by this job's token) so a retry resumes only
            // the card that failed instead of regenerating the whole piece.
            $progress = new KieProgress($this->token);
            $kind['_progress'] = $progress;
            $cor = (string) (config('contentmachine.plataformas_meta.'.$this->plataforma.'.cor') ?? '#1f7a7a');

            $plano = PublicacaoPlan::daOficina(
                $kinds->formato($this->tipo) === 'carousel',
                $this->titulo,
                $this->legenda,
                $this->slides,
                [$this->tipo, $this->plataforma],
            );

            if ($plano->slides === []) {
                Cache::put(self::key($this->token), ['imagens' => []], now()->addMinutes(30));

                return;
            }

            $paths = $this->escrever($renderer->render($plano, array_merge($kind, ['_cor' => $cor])));
            Cache::put(self::key($this->token), ['imagens' => $paths], now()->addMinutes(30));
            $progress->clear(); // piece finished — drop the resume state

            // Persist on the note (so the dashboard reflects the images without the workshop open).
            if ($this->notaSlug !== '' && $paths !== []) {
                $this->persistirNota($paths);
            }
        } finally {
            $this->limparFlag();
        }
    }

    public function failed(\Throwable $e): void
    {
        Cache::put(self::key($this->token), ['erro' => true, 'msg' => $e->getMessage()], now()->addMinutes(30));
        // All retries exhausted — drop the resume state so a fresh request starts clean.
        (new KieProgress($this->token))->clear();
        $this->limparFlag();
    }

    private function persistirNota(array $paths): void
    {
        try {
            $vault = app(\App\Services\Vault\VaultContract::class);
            $nota = $vault->get('rascunhos/'.$this->notaSlug.'.md');
            if ($nota) {
                $vault->updateFrontmatter($nota->path, ['imagens' => $paths]);
            }
        } catch (\Throwable) {
            // does not block generation if persistence fails
        }
    }

    private function limparFlag(): void
    {
        if ($this->notaSlug !== '') {
            Cache::forget(self::notaKey($this->notaSlug));
        }
    }

    /** Cache key that marks a post as «generating» (for the dashboard). */
    public static function notaKey(string $slug): string
    {
        return 'publicacao.gerando.'.$slug;
    }

    /**
     * Persists each artefact durably: inline SVG → .svg file;
     * URL (kie.ai) → downloads the PNG to .png (kie URLs expire).
     *
     * @param  array<int,string>  $artefactos
     * @return array<int,string>  relative web paths
     */
    private function escrever(array $artefactos): array
    {
        $rel = 'media/publicacoes/'.$this->token;
        $dir = public_path($rel);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $caminhos = [];
        foreach ($artefactos as $i => $arte) {
            $n = $i + 1;

            if (str_starts_with(ltrim($arte), '<svg')) {
                $p = $rel.'/'.$n.'.svg';
                file_put_contents(public_path($p), $arte);
                $caminhos[] = $p;

                continue;
            }

            // Image URL (kie.ai) — download to a durable file.
            try {
                $bytes = Http::timeout(60)->get($arte)->body();
                $p = $rel.'/'.$n.'.png';
                file_put_contents(public_path($p), $bytes);
                $caminhos[] = $p;
            } catch (\Throwable) {
                $caminhos[] = $arte; // keep the URL as a last resort
            }
        }

        return $caminhos;
    }

    public static function key(string $token): string
    {
        return 'publicacao.imagens.'.$token;
    }
}
