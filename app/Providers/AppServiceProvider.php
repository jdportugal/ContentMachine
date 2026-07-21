<?php

namespace App\Providers;

use App\Services\Aggregation\YtDlpRunner;
use App\Services\Aggregation\YtDlpRunnerContract;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
