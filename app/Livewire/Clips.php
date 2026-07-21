<?php

namespace App\Livewire;

use App\Services\Shorts\ShortsClient;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Gerador de Clips')]
class Clips extends Component
{
    // Formulário "nova fonte".
    public string $novaFonte = '';

    public string $novaFonteTitulo = '';

    public string $novaFonteLingua = 'pt';

    // Formulários "adicionar clip", por slug de fonte.
    /** @var array<string,string> */
    public array $clipTitulo = [];

    /** @var array<string,string> */
    public array $clipInicio = [];

    /** @var array<string,string> */
    public array $clipFim = [];

    /** @var array<string,string> */
    public array $clipTags = [];

    // Editor de legendas do clip aberto.
    public ?string $clipAberto = null;

    /** @var array<int,array<string,mixed>> */
    public array $segmentos = [];

    /** @var array<string,mixed> */
    public array $estilo = [];

    public string $modoPalavra = 'karaoke';

    public ?string $mensagem = null;

    public ?string $erro = null;

    // --- Fontes -------------------------------------------------------

    public function adicionarFonte(ShortsPipeline $pipeline): void
    {
        $this->reset('mensagem', 'erro');

        $this->validate(
            ['novaFonte' => 'required|string'],
            ['novaFonte.required' => 'Indique o URL do vídeo.'],
        );

        $pipeline->criarFonte(trim($this->novaFonte), trim($this->novaFonteTitulo), $this->novaFonteLingua);

        $this->reset('novaFonte', 'novaFonteTitulo');
        $this->mensagem = 'Fonte adicionada.';
    }

    public function transcrever(string $path, ShortsPipeline $pipeline): void
    {
        $this->executar(fn () => $pipeline->transcreverFonte($path), 'Transcrição concluída.');
    }

    public function removerFonte(string $path, VaultContract $vault): void
    {
        $vault->delete($path);
    }

    // --- Clips --------------------------------------------------------

    public function adicionarClip(string $fontePath, string $slug, ShortsPipeline $pipeline): void
    {
        $this->reset('mensagem', 'erro');

        $inicio = trim($this->clipInicio[$slug] ?? '');
        $fim = trim($this->clipFim[$slug] ?? '');

        if ($inicio === '' || $fim === '') {
            $this->erro = 'Indique o início e o fim do clip.';

            return;
        }

        $tags = collect(preg_split('/[,\n]/', $this->clipTags[$slug] ?? ''))
            ->map(fn ($t) => trim($t))->filter()->values()->all();

        $pipeline->criarClip($fontePath, trim($this->clipTitulo[$slug] ?? ''), $inicio, $fim, $tags);

        unset($this->clipTitulo[$slug], $this->clipInicio[$slug], $this->clipFim[$slug], $this->clipTags[$slug]);
        $this->mensagem = 'Clip criado.';
    }

    public function sugerirIA(string $fontePath, ShortsPipeline $pipeline): void
    {
        $this->reset('mensagem', 'erro');

        try {
            $segmentos = $pipeline->sugerirSegmentos($fontePath);

            foreach ($segmentos as $s) {
                $pipeline->criarClip(
                    $fontePath,
                    (string) ($s['title'] ?? 'Clip'),
                    $s['start_time'] ?? 0,
                    $s['end_time'] ?? 0,
                    (array) ($s['tags'] ?? []),
                );
            }

            $this->mensagem = count($segmentos).' clips sugeridos pela IA.';
        } catch (\Throwable $e) {
            $this->erro = $e->getMessage();
        }
    }

    public function cortar(string $path, ShortsPipeline $pipeline): void
    {
        $this->executar(fn () => $pipeline->cortarClip($path), 'Clip cortado.');
    }

    public function regenerar(string $path, ShortsPipeline $pipeline): void
    {
        $this->reset('mensagem', 'erro');

        if ($this->clipAberto === $path) {
            $pipeline->guardarLegendas($path, $this->segmentosParaDados(), $this->estilo, $this->modoPalavra);
        }

        $this->executar(fn () => $pipeline->gravarLegendas($path), 'Short gravado com as legendas.');
    }

    public function removerClip(string $path, VaultContract $vault): void
    {
        $vault->delete($path);

        if ($this->clipAberto === $path) {
            $this->fechar();
        }
    }

    // --- Editor de legendas ------------------------------------------

    public function abrir(string $path, VaultContract $vault, ShortsPipeline $pipeline): void
    {
        $clip = $vault->get($path);

        if (! $clip) {
            return;
        }

        $this->clipAberto = $path;
        $this->segmentos = array_values($pipeline->subtitleData($clip));
        $this->estilo = (array) $clip->get('estilo', ShortsPipeline::estiloPorDefeito());
        $this->modoPalavra = (string) $clip->get('modo_palavra', 'karaoke');
    }

    public function fechar(): void
    {
        $this->reset('clipAberto', 'segmentos', 'estilo', 'modoPalavra');
    }

    public function adicionarSegmento(): void
    {
        $this->segmentos[] = ['start' => 0, 'end' => 2, 'text' => '', 'words' => []];
    }

    public function removerSegmento(int $i): void
    {
        unset($this->segmentos[$i]);
        $this->segmentos = array_values($this->segmentos);
    }

    public function guardarLegendas(ShortsPipeline $pipeline): void
    {
        $this->reset('mensagem', 'erro');

        if ($this->clipAberto === null) {
            return;
        }

        $pipeline->guardarLegendas($this->clipAberto, $this->segmentosParaDados(), $this->estilo, $this->modoPalavra);
        $this->mensagem = 'Legendas guardadas.';
    }

    // --- Auxiliares ---------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    private function segmentosParaDados(): array
    {
        return collect($this->segmentos)->map(fn ($s) => [
            'start' => (float) ($s['start'] ?? 0),
            'end' => (float) ($s['end'] ?? 0),
            'text' => (string) ($s['text'] ?? ''),
            'words' => $s['words'] ?? [],
        ])->values()->all();
    }

    private function executar(callable $accao, string $sucesso): void
    {
        $this->reset('mensagem', 'erro');

        try {
            $accao();
            $this->mensagem = $sucesso;
        } catch (\Throwable $e) {
            $this->erro = $e->getMessage();
        }
    }

    public function render(VaultContract $vault, ShortsClient $client)
    {
        $clips = $vault->all(ShortsPipeline::CLIPES, recursive: false);

        return view('livewire.clips', [
            'fontes' => $vault->all(ShortsPipeline::FONTES),
            'clipsPorFonte' => $clips->groupBy(fn ($c) => (string) $c->get('fonte_path')),
            'temOpenAI' => filled(config('services.openai.key')),
            'apiUrl' => $client->baseUrl(),
            'modosPalavra' => ['karaoke', 'popup', 'typewriter', 'off'],
            'posicoes' => [
                'top-center', 'center-center', 'bottom-center',
                'top-left', 'center-left', 'bottom-left',
                'top-right', 'center-right', 'bottom-right',
            ],
        ]);
    }
}
