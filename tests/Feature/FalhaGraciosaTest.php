<?php

namespace Tests\Feature;

use App\Jobs\AgregarConteudoJob;
use App\Jobs\GerarRelatorioJob;
use App\Livewire\Noticias;
use App\Services\Support\JobFailure;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Two ways a run used to die silently: the provider refused the call (limit or
 * credits) and the page blamed the worker, or the worker was killed and the page
 * simply spun forever. Both must end with the user reading what actually
 * happened.
 */
class FalhaGraciosaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->comSessaoIniciada();
    }

    public function test_um_limite_do_fornecedor_e_reconhecido_como_tal(): void
    {
        $limites = [
            'API error 429: rate limit exceeded',
            'You have insufficient credits for this request',
            'HTTP 402 Payment Required',
            'Too Many Requests',
            'Error: usage limit reached',
        ];
        foreach ($limites as $m) {
            $this->assertTrue(JobFailure::ehLimite(new RuntimeException($m)), "devia reconhecer: {$m}");
            $this->assertStringContainsString('limit reached or credits exhausted', JobFailure::motivo(new RuntimeException($m)));
        }

        // Uma falha normal não deve ser confundida com um limite.
        $outro = new RuntimeException('Remotion failed: could not open the browser');
        $this->assertFalse(JobFailure::ehLimite($outro));
        $this->assertStringContainsString('Remotion failed', JobFailure::motivo($outro));
    }

    public function test_a_pagina_mostra_o_motivo_real_em_vez_de_culpar_o_worker(): void
    {
        $token = 'tok-limite';
        (new AgregarConteudoJob($token))->failed(new RuntimeException('API error 429: rate limit exceeded'));

        $guardado = Cache::get(AgregarConteudoJob::key($token));
        $this->assertTrue($guardado['erro']);
        $this->assertStringContainsString('limit reached or credits exhausted', $guardado['motivo']);

        Livewire::test(Noticias::class)
            ->set('aAgregar', true)
            ->set('agregacaoToken', $token)
            ->call('verificarAgregacao')
            ->assertSet('aAgregar', false)
            ->assertSet('avisoRelatorio', fn ($v) => str_contains((string) $v, 'credits exhausted')
                && ! str_contains((string) $v, 'queue:work'));
    }

    public function test_o_relatorio_tambem_mostra_o_motivo(): void
    {
        $token = 'tok-relatorio';
        (new GerarRelatorioJob('dia', '2026-08-24', $token, 'pt'))
            ->failed(new RuntimeException('insufficient credits'));

        Livewire::test(Noticias::class)
            ->set('aGerar', true)
            ->set('relatorioToken', $token)
            ->call('verificarRelatorio')
            ->assertSet('aGerar', false)
            ->assertSet('avisoRelatorio', fn ($v) => str_contains((string) $v, 'credits exhausted'));
    }

    /**
     * O worker foi morto a meio: nunca chega a failed(), portanto não há resumo
     * nem erro em cache. A página tem de desistir em vez de rodar para sempre.
     */
    public function test_a_pagina_desiste_quando_o_worker_morre_a_meio(): void
    {
        Livewire::test(Noticias::class)
            ->set('aAgregar', true)
            ->set('agregacaoToken', 'tok-orfao')   // nada em cache, nenhum slot tomado
            ->call('verificarAgregacao')
            ->assertSet('aAgregar', false)
            ->assertSet('avisoRelatorio', fn ($v) => str_contains((string) $v, 'no longer running'));
    }

    /** Enquanto a recolha REALMENTE corre, a página continua à espera. */
    public function test_enquanto_corre_a_pagina_continua_a_esperar(): void
    {
        $token = 'tok-a-correr';
        $this->assertTrue(AgregarConteudoJob::reservar($token));

        Livewire::test(Noticias::class)
            ->set('aAgregar', true)
            ->set('agregacaoToken', $token)
            ->call('verificarAgregacao')
            ->assertSet('aAgregar', true)          // ainda a correr — não desiste
            ->assertSet('avisoRelatorio', null);
    }
}
