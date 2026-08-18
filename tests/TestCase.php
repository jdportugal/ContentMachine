<?php

namespace Tests;

use App\Models\User;
use App\Services\Projects\ProjectContext;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private ?string $vaultTemp = null;

    private ?string $remotionTempBase = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate the vault so tests — and the ClipProject observer that mirrors
        // projects into the vault — never write to the real ./vault. Individual
        // tests may still rebind VaultContract to their own temp directory.
        $this->vaultTemp = sys_get_temp_dir().'/cm-test-vault-'.uniqid();
        mkdir($this->vaultTemp, 0775, true);
        config(['contentmachine.vault.path' => $this->vaultTemp]);
        // Isolate the project layer too: the default project points at the temp
        // vault, and the registry/new-project root live under it (never storage/).
        config([
            'contentmachine.projects.default_vault' => $this->vaultTemp,
            'contentmachine.projects.registry' => $this->vaultTemp.'/projects.json',
            'contentmachine.projects.root' => $this->vaultTemp.'/_projects',
            // Shared API keys live outside the vault — isolate them per test too.
            'contentmachine.settings.keys_path' => $this->vaultTemp.'/settings-keys.json',
        ]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->vaultTemp));
        // ProjectContext may have resolved its default (real vault) during boot,
        // before the config above. Reset it so it re-reads the temp paths.
        $this->app->forgetInstance(ProjectContext::class);

        // Isolate the Remotion project too. EffectLibrary/BackgroundLibrary
        // ::syncFilesystem() REBUILDS remotion/src/effects from the active vault's
        // records and deletes anything else it finds there. The vault above is
        // empty, so any test that reaches syncFilesystem — RenderJob does, and it
        // is called directly by several tests — would wipe the developer's real
        // generated components. Point it somewhere disposable for every test;
        // individual tests may still override it with their own temp dir.
        $this->remotionTempBase = sys_get_temp_dir().'/cm-test-remotion-'.uniqid();
        mkdir($this->remotionTempBase.'/src/effects', 0775, true);
        mkdir($this->remotionTempBase.'/src/backgrounds', 0775, true);
        config(['contentmachine.clips.remotion_path' => $this->remotionTempBase]);
    }

    /**
     * Sign in, so a test can reach the app: every route in routes/web.php now
     * requires a session (see Authenticate in bootstrap/app.php).
     *
     * The user is deliberately NOT persisted — actingAs() puts it straight on the
     * guard, so this works in the tests that have no database at all. Guest
     * behaviour is covered on purpose in AutenticacaoTest, not here.
     */
    protected function comSessaoIniciada(): static
    {
        return $this->actingAs(new User(['name' => 'Test', 'email' => 'test@example.test']));
    }

    protected function tearDown(): void
    {
        if ($this->vaultTemp && is_dir($this->vaultTemp)) {
            $this->rrmdir($this->vaultTemp);
        }
        if ($this->remotionTempBase && is_dir($this->remotionTempBase)) {
            $this->rrmdir($this->remotionTempBase);
        }

        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir.'/'.$f;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
