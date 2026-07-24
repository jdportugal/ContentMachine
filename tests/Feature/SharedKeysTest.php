<?php

namespace Tests\Feature;

use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Tests\TestCase;

class SharedKeysTest extends TestCase
{
    public function test_api_keys_are_shared_across_projects_but_other_settings_are_not(): void
    {
        // Use the real project-aware vault binding (TestCase pins it to one dir).
        $this->app->bind(VaultContract::class, fn ($app) => new VaultRepository($app->make(ProjectContext::class)->vaultPath()));

        // In the default project: set an API key AND a per-project channel.
        app(SettingsRepository::class)->save([
            'chaves' => ['anthropic' => 'KEY-123'],
            'canais' => ['youtube' => ['https://youtube.com/@only-in-default']],
        ]);

        // Switch to a brand-new project.
        $other = app(ProjectRepository::class)->create('Brand B', 'en');
        app(ProjectContext::class)->set($other);

        $all = app(SettingsRepository::class)->all();

        // Key is shared → visible in the other project.
        $this->assertSame('KEY-123', $all['chaves']['anthropic']);
        // Channel is per-project → the other project does NOT see it.
        $this->assertNotContains('https://youtube.com/@only-in-default', $all['canais']['youtube']);
    }

    public function test_keys_are_not_written_into_the_per_project_vault_note(): void
    {
        app(SettingsRepository::class)->save(['chaves' => ['openai' => 'SECRET']]);

        $note = app(VaultContract::class)->get('definicoes/definicoes.md');
        // The vault note keeps its default (empty) keys — the real value lives in the shared store.
        $this->assertNotSame('SECRET', $note?->get('chaves')['openai'] ?? null);
        $this->assertSame('SECRET', app(SettingsRepository::class)->all()['chaves']['openai']);
    }
}
