<?php

namespace Tests\Feature;

use App\Livewire\Definicoes;
use App\Services\Settings\SettingsOverlay;
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

    public function test_saving_an_elevenlabs_voice_id_overlays_the_clip_voice_config(): void
    {
        Livewire::test(Definicoes::class)
            ->set('modelos.elevenlabs_voice', 'VOICE-XYZ')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('VOICE-XYZ', app(SettingsRepository::class)->all()['modelos']['elevenlabs_voice']);

        app(SettingsOverlay::class)->apply(app(SettingsRepository::class));
        $this->assertSame('VOICE-XYZ', config('contentmachine.clips.voice_id'));
    }

    public function test_defaults_quando_vazio(): void
    {
        $s = app(SettingsRepository::class);

        $this->assertSame('Brand Machine', $s->get('geral.nome_marca'));
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

    public function test_saving_persists_every_tab_regardless_of_the_open_one(): void
    {
        // Values set across different tabs, with the API-keys tab left open on save,
        // must all persist (state is server-side; tabs are just a display filter).
        Livewire::test(Definicoes::class)
            ->set('geral.nome_marca', 'Brand Machine')     // General tab
            ->set('chaves.openai', 'sk-test')               // API Keys tab
            ->set('modelos.openai_model', 'gpt-4o-mini')    // AI & Engine tab
            ->set('secao', 'chaves')
            ->call('guardar')
            ->assertHasNoErrors();

        $s = app(SettingsRepository::class);
        $this->assertSame('Brand Machine', $s->get('geral.nome_marca'));
        $this->assertSame('sk-test', $s->get('chaves.openai'));
        $this->assertSame('gpt-4o-mini', $s->get('modelos.openai_model'));
    }

    public function test_componente_guarda_fontes_como_lista(): void
    {
        Livewire::test(Definicoes::class)
            ->set('geral.nome_marca', 'Brand Machine')
            ->set('fontes.youtube', "canal-a\ncanal-b\n\n")
            ->call('guardar')
            ->assertSet('guardado', fn ($v) => $v !== null);

        $this->assertSame(['canal-a', 'canal-b'], app(SettingsRepository::class)->get('agregador.youtube'));
    }

    /**
     * The keys must NEVER reach the browser. $chaves is a public Livewire
     * property, so anything loaded into it is serialised into the page — that is
     * exactly how they leaked. The page may say a key is set; never its value.
     */
    public function test_a_pagina_nunca_devolve_o_valor_das_chaves(): void
    {
        app(SettingsRepository::class)->save(['chaves' => ['openai' => 'sk-CANARIO-12345']]);

        Livewire::test(Definicoes::class)
            ->assertSet('chaves.openai', '')
            ->assertSet('chavesDefinidas.openai', true)
            ->assertDontSee('sk-CANARIO-12345');
    }

    /** A blank field means "keep the stored key", not "erase it". */
    public function test_guardar_com_campo_vazio_nao_apaga_a_chave(): void
    {
        app(SettingsRepository::class)->save(['chaves' => ['openai' => 'sk-mantida']]);

        Livewire::test(Definicoes::class)
            ->set('geral.nome_marca', 'Brand Machine')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('sk-mantida', app(SettingsRepository::class)->get('chaves.openai'));
    }

    /** …but removing one on purpose still works. */
    public function test_limpar_chave_remove_a_chave_guardada(): void
    {
        app(SettingsRepository::class)->save(['chaves' => ['openai' => 'sk-comprometida']]);

        Livewire::test(Definicoes::class)
            ->call('limparChave', 'openai')
            ->assertSet('chavesDefinidas.openai', false);

        $this->assertSame('', app(SettingsRepository::class)->get('chaves.openai'));
    }

    /** A second key for the same provider is added, not swapped in. */
    public function test_guardar_adiciona_uma_segunda_chave_ao_mesmo_fornecedor(): void
    {
        Livewire::test(Definicoes::class)
            ->set('chaves.openai', 'sk-primeira')
            ->set('rotulos.openai', 'Personal')
            ->call('guardar')
            ->set('chaves.openai', 'sk-segunda')
            ->set('rotulos.openai', 'Client')
            ->call('guardar')
            ->assertDontSee('sk-primeira')
            ->assertDontSee('sk-segunda');

        $guardadas = app(\App\Services\Settings\SharedKeys::class)->entries()['openai'];
        $this->assertSame(['Personal', 'Client'], array_column($guardadas, 'label'));
        // The first stays the provider default.
        $this->assertSame('sk-primeira', app(SettingsRepository::class)->get('chaves.openai'));
    }

    /** Pinning a step to a key round-trips through the Steps tab. */
    public function test_a_ligacao_de_um_passo_a_uma_chave_persiste(): void
    {
        $id = app(\App\Services\Settings\SharedKeys::class)->add('openai', 'sk-plan', 'Plan');

        Livewire::test(Definicoes::class)
            ->set('secao', 'passos')
            ->set('passos.clips_plano', $id)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSee('Clips · animation plan');

        $this->assertSame($id, app(SettingsRepository::class)->get('passos.clips_plano'));
    }
}
