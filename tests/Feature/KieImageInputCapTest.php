<?php

namespace Tests\Feature;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\Rendering\KieClient;
use App\Services\Publicacoes\Rendering\KiePromptComposer;
use App\Services\Publicacoes\Rendering\KieSlideRenderer;
use Tests\TestCase;

class KieImageInputCapTest extends TestCase
{
    public function test_image_input_nunca_excede_8_num_carrossel_grande(): void
    {
        // KieClient falso: regista quantas referências recebe cada generate().
        $fake = new class extends KieClient
        {
            /** @var array<int,int> */
            public array $contagens = [];

            public function configurado(): bool
            {
                return true;
            }

            public function carregarReferencias(array $caminhos): array
            {
                return array_values($caminhos); // finge que já são URLs
            }

            public function generate(string $prompt, string $proporcao, array $refs = []): string
            {
                $this->contagens[] = count($refs);

                return 'https://kie.example/'.count($this->contagens).'.png';
            }

            public function upload(string $bytes, string $nome): string
            {
                return 'https://kie.example/upload.png';
            }
        };

        $renderer = new KieSlideRenderer($fake, app(KiePromptComposer::class));

        $slides = [];
        for ($i = 1; $i <= 10; $i++) {
            $slides[] = new SlidePlano($i, 'Cartão '.$i, 'texto '.$i);
        }

        $renderer->render(new PublicacaoPlan('Título', '', [], $slides), [
            'proporcao' => '1:1',
            '_refs' => ['media/refs/a.png', 'media/refs/b.png', 'media/refs/c.png'],
            '_anexos' => [0 => ['media/refs/x.png'], 5 => ['media/refs/y.png', 'media/refs/z.png']],
            '_anexosDescr' => [],
            '_prompts' => [],
        ]);

        $this->assertCount(10, $fake->contagens);
        foreach ($fake->contagens as $ordem => $n) {
            $this->assertLessThanOrEqual(8, $n, "cartão {$ordem} enviou {$n} referências (>8)");
        }
        // O último cartão tem muitas páginas anteriores → enche o limite (8).
        $this->assertSame(8, end($fake->contagens));
    }
}
