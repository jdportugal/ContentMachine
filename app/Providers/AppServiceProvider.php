<?php

namespace App\Providers;

use App\Services\Settings\SettingsRepository;
use App\Services\Shorts\ShortsClient;
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

        // Cliente da API ShortsCreator: URL das definições (vault) ou do .env.
        $this->app->bind(ShortsClient::class, function ($app) {
            $url = $app->make(SettingsRepository::class)->get('shorts.api_url');
            $url = filled($url) ? $url : config('services.shorts.base_url');

            return new ShortsClient(rtrim((string) $url, '/'));
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
