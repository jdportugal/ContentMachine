<?php

namespace Tests;

use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private ?string $vaultTemp = null;

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
        ]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->vaultTemp));
    }

    protected function tearDown(): void
    {
        if ($this->vaultTemp && is_dir($this->vaultTemp)) {
            $this->rrmdir($this->vaultTemp);
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
