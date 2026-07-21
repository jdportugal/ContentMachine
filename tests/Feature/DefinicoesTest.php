<?php

namespace Tests\Feature;

use App\Livewire\Definicoes;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Livewire\Livewire;
use Tests\TestCase;

class DefinicoesTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-def-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.'/definicoes/*.md') ?: []);
        parent::tearDown();
    }

    public function test_defaults_quando_vazio(): void
    {
        $s = app(SettingsRepository::class);

        $this->assertSame('IATECA', $s->get('geral.nome_marca'));
        $this->assertSame([], $s->get('agregador.reddit'));
    }

    public function test_save_e_get_em_ida_e_volta(): void
    {
        $s = app(SettingsRepository::class);

        $s->save([
            'geral' => ['nome_marca' => 'Casa Nova'],
            'agregador' => ['reddit' => ['r/artificial', 'r/portugal']],
        ]);

        $this->assertSame('Casa Nova', $s->get('geral.nome_marca'));
        $this->assertSame(['r/artificial', 'r/portugal'], $s->get('agregador.reddit'));
        // Preserva defaults não fornecidos.
        $this->assertArrayHasKey('youtube', $s->get('perfis'));
    }

    public function test_componente_guarda_fontes_como_lista(): void
    {
        Livewire::test(Definicoes::class)
            ->set('geral.nome_marca', 'IATECA')
            ->set('fontes.youtube', "canal-a\ncanal-b\n\n")
            ->call('guardar')
            ->assertSet('guardado', fn ($v) => $v !== null);

        $this->assertSame(['canal-a', 'canal-b'], app(SettingsRepository::class)->get('agregador.youtube'));
    }
}
