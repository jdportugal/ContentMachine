<?php

namespace App\Livewire\Publicacoes;

use App\Jobs\GerarImagensJob;
use App\Jobs\PlanearPublicacaoJob;
use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Vault\VaultContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
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

    /** Redação em curso: token do plano em fila + estado da sondagem. */
    public ?string $planToken = null;

    public bool $aRedigir = false;

    /** Desenho de imagens em curso. */
    public ?string $imgToken = null;

    public bool $aGerar = false;

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

    /**
     * Despacha a redação para uma fila e passa a sondar o resultado. A geração
     * corre num worker («php artisan queue:work»), onde o CLI do Claude autentica
     * — ao contrário do processo do servidor web. Se a fila for síncrona, o
     * trabalho corre já e o resultado é aplicado de imediato.
     */
    public function redigirComIa(): void
    {
        $this->aviso = null;

        if (trim($this->brief) === '') {
            $this->addError('brief', 'Escreva um tema ou brief para a IA desenvolver.');

            return;
        }

        $this->planToken = (string) Str::uuid();
        $this->aRedigir = true;
        $this->aviso = 'A IA está a redigir… (requer um worker: «php artisan queue:work»).';

        PlanearPublicacaoJob::dispatch($this->tipo, $this->brief, $this->plataforma, $this->planToken);

        // Fila síncrona (ex.: testes): o resultado já está pronto.
        $this->verificarPlano();
    }

    /** Sondado por wire:poll enquanto $aRedigir: aplica o plano quando pronto. */
    public function verificarPlano(): void
    {
        if (! $this->aRedigir || $this->planToken === null) {
            return;
        }

        $r = Cache::get(PlanearPublicacaoJob::key($this->planToken));

        if ($r === null) {
            return; // ainda a processar
        }

        $this->aRedigir = false;
        Cache::forget(PlanearPublicacaoJob::key($this->planToken));

        if (! empty($r['erro'])) {
            $this->aviso = 'A redação falhou. Verifique se o worker está a correr e tente de novo.';

            return;
        }

        if ($this->titulo === '') {
            $this->titulo = (string) ($r['titulo'] ?? '');
        }

        if ($this->ehCarrossel()) {
            $this->slides = array_map(
                fn ($s) => ['titulo' => (string) ($s['titulo'] ?? ''), 'texto' => (string) ($s['texto'] ?? '')],
                $r['slides'] ?? [],
            );
        } else {
            $this->legenda = ($r['legenda'] ?? '') !== ''
                ? (string) $r['legenda']
                : (string) ($r['slides'][0]['texto'] ?? '');
        }

        $this->aviso = ($r['fonte'] ?? null) === 'ia'
            ? 'Redigido pela IA ('.($r['fornecedor'] ?: 'LLM').'). Reveja e ajuste antes de guardar.'
            : 'IA indisponível neste contexto — gerei um rascunho local a partir do seu texto. É propositadamente básico; para redação forte, corra «php artisan queue:work» num terminal com a sua sessão do Claude.';
    }

    public function cancelarRedacao(): void
    {
        $this->aRedigir = false;
        $this->planToken = null;
        $this->aviso = null;
    }

    /**
     * Despacha o desenho dos cartões para a fila (o kie.ai/nano-banana-pro é
     * lento demais para o pedido web) e passa a sondar. Fila síncrona → pronto já.
     */
    public function gerarImagens(): void
    {
        $plano = $this->planoAtual();

        if ($plano->slides === []) {
            $this->addError('brief', 'Componha a peça antes de gerar imagens.');

            return;
        }

        $this->imgToken = (string) Str::uuid();
        $this->aGerar = true;

        GerarImagensJob::dispatch(
            $this->tipo, $this->titulo, $this->plataforma, $this->legenda, $this->slides, $this->imgToken,
        );

        $this->verificarImagens();
    }

    /** Sondado por wire:poll enquanto $aGerar: mostra as imagens quando prontas. */
    public function verificarImagens(): void
    {
        if (! $this->aGerar || $this->imgToken === null) {
            return;
        }

        $r = Cache::get(GerarImagensJob::key($this->imgToken));

        if ($r === null) {
            return;
        }

        $this->aGerar = false;
        Cache::forget(GerarImagensJob::key($this->imgToken));

        if (! empty($r['erro'])) {
            $this->addError('slides', 'O desenho das imagens falhou. Confirme o worker e a chave do kie.ai.');

            return;
        }

        $this->previews = $r['imagens'] ?? [];
    }

    public function cancelarImagens(): void
    {
        $this->aGerar = false;
        $this->imgToken = null;
    }

    public function criarRascunho(VaultContract $vault): void
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

        // Reutiliza as imagens já desenhadas (ficheiros duráveis em public/media).
        if ($this->previews !== []) {
            $vault->updateFrontmatter($nota->path, ['imagens' => array_values($this->previews)]);
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
        return PublicacaoPlan::daOficina(
            $this->ehCarrossel(),
            $this->titulo,
            $this->legenda,
            $this->slides,
            [$this->tipo, $this->plataforma],
        );
    }

    public function render()
    {
        return view('livewire.publicacoes.oficina')
            ->title(($this->kind['label'] ?? 'Publicação'));
    }
}
