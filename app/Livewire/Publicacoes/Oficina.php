<?php

namespace App\Livewire\Publicacoes;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Publicacoes\PublicacaoPlanner;
use App\Services\Publicacoes\Rendering\SlideRenderer;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Oficina genérica de publicações: compõe, planeia com IA e desenha em imagem
 * qualquer TIPO do registo (config publicacoes.tipos). Substitui as oficinas
 * dedicadas de posts e carrosséis por uma única, guiada pela configuração.
 */
#[Layout('components.layouts.app')]
class Oficina extends Component
{
    public string $tipo = '';

    public string $titulo = '';

    public string $plataforma = 'instagram';

    public string $brief = '';

    public string $legenda = '';

    /** @var array<int,array{titulo:string,texto:string}> cartões (carrossel) */
    public array $slides = [];

    /** @var array<int,string> pré-visualizações (SVG inline ou URL) */
    public array $previews = [];

    public ?string $guardado = null;

    public ?string $aviso = null;

    public function mount(string $tipo, PublicacaoKinds $kinds): void
    {
        abort_unless($kinds->exists($tipo), 404);

        $this->tipo = $tipo;
        $def = $kinds->get($tipo);
        $this->plataforma = (string) ($def['plataforma_padrao'] ?? 'instagram');

        if ($this->ehCarrossel()) {
            $min = max(2, $kinds->cartoes($tipo)['min']);
            $this->slides = array_fill(0, $min, ['titulo' => '', 'texto' => '']);
        }
    }

    // --------------------------------------------------------------- helpers

    private function kinds(): PublicacaoKinds
    {
        return app(PublicacaoKinds::class);
    }

    /** @return array<string,mixed> */
    #[Computed]
    public function kind(): array
    {
        return $this->kinds()->get($this->tipo) ?? [];
    }

    public function ehCarrossel(): bool
    {
        return $this->kinds()->formato($this->tipo) === 'carousel';
    }

    private function cor(): string
    {
        return (string) (config('contentmachine.plataformas_meta.'.$this->plataforma.'.cor') ?? '#1f7a7a');
    }

    /** Definição do tipo enriquecida com a cor de acento da plataforma. */
    private function kindCor(): array
    {
        return array_merge($this->kind, ['_cor' => $this->cor()]);
    }

    // ---------------------------------------------------------------- acções

    public function adicionarSlide(): void
    {
        $max = $this->kinds()->cartoes($this->tipo)['max'];
        if (count($this->slides) < $max) {
            $this->slides[] = ['titulo' => '', 'texto' => ''];
        }
    }

    public function removerSlide(int $i): void
    {
        $min = $this->kinds()->cartoes($this->tipo)['min'];
        if (count($this->slides) > $min) {
            unset($this->slides[$i]);
            $this->slides = array_values($this->slides);
        }
    }

    public function redigirComIa(PublicacaoPlanner $planner): void
    {
        $this->aviso = null;

        if (trim($this->brief) === '') {
            $this->addError('brief', 'Escreva um tema ou brief para a IA desenvolver.');

            return;
        }

        $plano = $planner->planear($this->tipo, $this->brief, $this->plataforma);

        if ($this->titulo === '') {
            $this->titulo = $plano->titulo;
        }

        if ($this->ehCarrossel()) {
            $this->slides = array_map(
                fn (SlidePlano $s) => ['titulo' => $s->titulo, 'texto' => $s->texto],
                $plano->slides,
            );
        } else {
            $this->legenda = $plano->legenda !== '' ? $plano->legenda : ($plano->slides[0]->texto ?? '');
        }

        $this->aviso = 'Rascunho redigido pela IA. Reveja e ajuste antes de guardar.';
    }

    public function gerarImagens(SlideRenderer $renderer): void
    {
        $plano = $this->planoAtual();

        if ($plano->slides === []) {
            $this->addError('brief', 'Componha a peça antes de gerar imagens.');

            return;
        }

        $this->previews = $renderer->render($plano, $this->kindCor());
    }

    public function criarRascunho(VaultContract $vault, SlideRenderer $renderer): void
    {
        $this->validate($this->rules());

        $plano = $this->planoAtual();

        if ($this->ehCarrossel() && count($plano->slides) < 2) {
            $this->addError('slides', 'Um carrossel precisa de pelo menos 2 cartões com texto.');

            return;
        }

        $formato = $this->kinds()->formato($this->tipo);

        $nota = $vault->create('rascunhos', [
            'titulo' => $this->titulo,
            'tipo' => $this->tipo,
            'formato' => $formato,
            'gabarito' => (string) ($this->kind['gabarito'] ?? ''),
            'plataforma' => $this->plataforma,
            'estado' => 'rascunho',
            'origem' => 'publicacoes/oficina',
            'cartoes' => count($plano->slides),
            'tags' => array_values(array_unique([$this->tipo, $this->plataforma])),
        ], $plano->toBody($formato));

        $imagens = $this->escreverImagens($nota->slug(), $renderer->render($plano, $this->kindCor()));
        if ($imagens !== []) {
            $vault->updateFrontmatter($nota->path, ['imagens' => $imagens]);
        }

        $this->guardado = $nota->title();
        $this->previews = [];
        $this->reset('brief');
        if ($this->ehCarrossel()) {
            $this->slides = array_fill(0, max(2, $this->kinds()->cartoes($this->tipo)['min']), ['titulo' => '', 'texto' => '']);
            $this->titulo = '';
        } else {
            $this->reset('titulo', 'legenda');
        }
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        $regras = [
            'titulo' => 'required|string|min:3|max:120',
            'plataforma' => 'required|in:instagram,linkedin,tiktok,youtube',
        ];

        if (! $this->ehCarrossel()) {
            $regras['legenda'] = 'required|string|min:3';
        }

        return $regras;
    }

    /** Constrói um plano a partir do estado actual do formulário (sem IA). */
    private function planoAtual(): PublicacaoPlan
    {
        if ($this->ehCarrossel()) {
            $slides = [];
            foreach ($this->slides as $s) {
                $titulo = trim((string) ($s['titulo'] ?? ''));
                $texto = trim((string) ($s['texto'] ?? ''));
                if ($titulo === '' && $texto === '') {
                    continue;
                }
                $slides[] = new SlidePlano(count($slides) + 1, $titulo !== '' ? $titulo : 'Cartão '.(count($slides) + 1), $texto);
            }

            return new PublicacaoPlan($this->titulo, '', [$this->tipo, $this->plataforma], $slides);
        }

        return new PublicacaoPlan(
            titulo: $this->titulo,
            legenda: $this->legenda,
            tags: [$this->tipo, $this->plataforma],
            slides: [new SlidePlano(1, $this->titulo !== '' ? $this->titulo : 'Peça', $this->legenda)],
        );
    }

    /**
     * Escreve cada artefacto: SVG inline → ficheiro em public/media; URL → guarda tal e qual.
     *
     * @param  array<int,string>  $artefactos
     * @return array<int,string>  caminhos web relativos
     */
    private function escreverImagens(string $slug, array $artefactos): array
    {
        $dir = public_path('media/publicacoes/'.$slug);
        $caminhos = [];

        foreach ($artefactos as $i => $arte) {
            if (! str_starts_with(ltrim($arte), '<svg')) {
                $caminhos[] = $arte; // URL de imagem (ex.: kie.ai)

                continue;
            }

            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $rel = 'media/publicacoes/'.$slug.'/'.($i + 1).'.svg';
            file_put_contents(public_path($rel), $arte);
            $caminhos[] = $rel;
        }

        return $caminhos;
    }

    public function render()
    {
        return view('livewire.publicacoes.oficina')
            ->title(($this->kind['label'] ?? 'Publicação'));
    }
}
