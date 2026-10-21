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

}
