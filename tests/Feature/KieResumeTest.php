<?php

namespace Tests\Feature;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\Rendering\KieClient;
use App\Services\Publicacoes\Rendering\KiePromptComposer;
use App\Services\Publicacoes\Rendering\KieProgress;
use App\Services\Publicacoes\Rendering\KieSlideRenderer;
use Tests\TestCase;

class KieResumeTest extends TestCase
{
    /**
     * On a resumed piece: a finished card is reused (no kie call), an in-flight card
     * is re-polled by its taskId (NOT re-submitted → kie doesn't regenerate it), and
     * only a never-started card is submitted. So a retry fetches just the failed card.
     */
    public function test_render_resumes_only_the_unfinished_cards(): void
    {
        $fake = new class extends KieClient
        {
            /** @var array<int,string> */
            public array $submitted = [];

            /** @var array<int,string> */
            public array $polled = [];

            /** @var array<int,string> */
            public array $generated = [];

            public function configurado(): bool
            {
                return true;
            }

            public function carregarReferencias(array $caminhos): array
            {
                return array_values($caminhos);
            }

            public function submit(string $prompt, string $proporcao, array $refs = []): string
            {
                $this->submitted[] = $prompt;

                return 'task-'.count($this->submitted);
            }

            public function poll(string $taskId): string
            {
                $this->polled[] = $taskId;

                return 'https://kie.example/'.$taskId.'.png';
            }

            public function generate(string $prompt, string $proporcao, array $refs = []): string
            {
                $this->generated[] = $prompt;

                return 'https://kie.example/gen.png';
            }
        };

        $renderer = new KieSlideRenderer($fake, app(KiePromptComposer::class));

        $progress = new KieProgress('tok-1');
        $progress->save(0, ['taskId' => 'old-0', 'url' => 'https://kie.example/done-0.png']); // finished
        $progress->save(1, ['taskId' => 'old-1']); // in-flight: submitted but never finished
        // card 2: nothing yet

        $slides = [
            new SlidePlano(1, 'A', 'a'),
            new SlidePlano(2, 'B', 'b'),
            new SlidePlano(3, 'C', 'c'),
        ];

        $urls = $renderer->render(new PublicacaoPlan('Título', '', [], $slides), [
            'proporcao' => '1:1',
            '_refs' => [], '_anexos' => [], '_anexosDescr' => [], '_prompts' => [],
            '_progress' => $progress,
        ]);

        // Finished card reused verbatim, no kie call.
        $this->assertSame('https://kie.example/done-0.png', $urls[0]);
        // Only the never-started card (index 2) is submitted — NOT the in-flight one.
        $this->assertCount(1, $fake->submitted);
        // In-flight card resumed by its existing taskId; fresh card polled once too.
        $this->assertSame(['old-1', 'task-1'], $fake->polled);
        // The resumable path never falls back to a full generate().
        $this->assertSame([], $fake->generated);
        $this->assertCount(3, $urls);

        // Progress now records URLs for the two cards it finished this run.
        $this->assertSame('https://kie.example/old-1.png', $progress->card(1)['url']);
        $this->assertSame('https://kie.example/task-1.png', $progress->card(2)['url']);
    }

    /** Without a progress store, render() behaves exactly as before (plain generate). */
    public function test_render_without_progress_uses_generate(): void
    {
        $fake = new class extends KieClient
        {
            public int $generateCalls = 0;

            public function configurado(): bool
            {
                return true;
            }

            public function carregarReferencias(array $caminhos): array
            {
                return array_values($caminhos);
            }

            public function generate(string $prompt, string $proporcao, array $refs = []): string
            {
                $this->generateCalls++;

                return 'https://kie.example/'.$this->generateCalls.'.png';
            }
        };

        $renderer = new KieSlideRenderer($fake, app(KiePromptComposer::class));
        $urls = $renderer->render(new PublicacaoPlan('T', '', [], [new SlidePlano(1, 'A', 'a'), new SlidePlano(2, 'B', 'b')]), [
            'proporcao' => '1:1', '_refs' => [], '_anexos' => [], '_anexosDescr' => [], '_prompts' => [],
        ]);

        $this->assertCount(2, $urls);
        $this->assertSame(2, $fake->generateCalls);
    }
}
