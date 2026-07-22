<?php

namespace App\Providers;

use App\Services\Aggregation\YtDlpRunner;
use App\Services\Aggregation\YtDlpRunnerContract;
use App\Services\Publicacoes\Rendering\KieSlideRenderer;
use App\Services\Publicacoes\Rendering\SlideRenderer;
use App\Services\Publicacoes\Rendering\SvgSlideRenderer;
use App\Services\Shorts\LocalVideoEngine;
use App\Services\Shorts\MusicLibrary;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // O vault (cérebro/memória) é um singleton apontado à pasta configurada.
        $this->app->singleton(VaultContract::class, function () {
            return new VaultRepository(config('contentmachine.vault.path'));
        });

        $this->app->alias(VaultContract::class, VaultRepository::class);

        // Runner do yt-dlp (agregador). Ligado a uma interface para permitir
        // substituição por um duplo de teste nos testes.
        $this->app->bind(YtDlpRunnerContract::class, YtDlpRunner::class);

        // Renderizador de cartões das publicações: 'kie' (kie.ai, requer chave)
        // ou 'svg' (offline, determinístico) por omissão.
        $this->app->bind(SlideRenderer::class, function () {
            $driver = (string) config('contentmachine.publicacoes.render_driver', 'svg');

            return $driver === 'kie' && filled(config('services.kie.key'))
                ? $this->app->make(KieSlideRenderer::class)
                : new SvgSlideRenderer;
        });

        // Motor local e independente (ffmpeg + Whisper) — sem API externa.
        $this->app->singleton(LocalVideoEngine::class, function () {
            $c = config('services.shorts');

            return new LocalVideoEngine(
                ffmpeg: $c['ffmpeg'] ?? 'ffmpeg',
                ffprobe: $c['ffprobe'] ?? 'ffprobe',
                python: $c['python'] ?? 'python3',
                transcribeScript: $c['transcribe_script'] ?? base_path('scripts/transcribe.py'),
                whisperModel: $c['whisper_model'] ?? 'tiny',
                fontsDir: $c['fonts_path'] ?? resource_path('fonts'),
            );
        });

        // Biblioteca de músicas de fundo (storage/app/shorts/musicas).
        $this->app->singleton(MusicLibrary::class, function () {
            return new MusicLibrary(storage_path('app/shorts/musicas'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
