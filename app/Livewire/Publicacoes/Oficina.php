<?php

namespace App\Livewire\Publicacoes;

use App\Jobs\GerarImagensJob;
use App\Jobs\PlanearPublicacaoJob;
use App\Jobs\RegenerarCartaoJob;
use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Vault\VaultContract;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Oficina genérica de publicações: compõe, planeia com IA e desenha em imagem
 * qualquer TIPO do registo (config publicacoes.tipos). Cada cartão tem a sua
 * imagem ao lado, com regeneração/edição por instrução e histórico de versões.
 */
#[Layout('components.layouts.app')]
class Oficina extends Component
{
    use WithFileUploads;

    public string $tipo = '';

    public string $titulo = '';

    public string $plataforma = 'instagram';

    /** Resolução (proporção) escolhida — predefinida pelo tipo. */
    public string $proporcao = '1:1';

    public string $brief = '';

    public string $legenda = '';

    /** @var array ficheiros a carregar (input) */
    public array $uploads = [];

    /** @var array<int,array{path:string,descricao:string}> imagens de referência */
    public array $referencias = [];

    /** @var array<int,array{titulo:string,texto:string}> cartões (carrossel) */
    public array $slides = [];

    // --- Imagens por cartão (índice 0..N-1; peça única usa índice 0) ---
    /** @var array<int,string> imagem actual (caminho web) por cartão */
    public array $img = [];

    /** @var array<int,string> instrução de edição por cartão */
    public array $editar = [];

    /** @var array<int,array<int,string>> histórico de versões por cartão (recente primeiro) */
    public array $hist = [];

    /** @var array<int,string> token de regeneração em curso por cartão */
    public array $gerando = [];

    public ?string $guardado = null;

    public ?string $aviso = null;

    /** Redação em curso. */
    public ?string $planToken = null;

    public bool $aRedigir = false;

    /** Desenho de TODAS as imagens em curso. */
    public ?string $imgToken = null;

    public bool $aGerar = false;

    /** Caminho da nota a editar (null = nova publicação). */
    public ?string $notaPath = null;

    public function mount(string $tipo, PublicacaoKinds $kinds, VaultContract $vault): void
    {
        abort_unless($kinds->exists($tipo), 404);

        $this->tipo = $tipo;
        $def = $kinds->get($tipo);
        $this->plataforma = (string) ($def['plataforma_padrao'] ?? 'instagram');
        $this->proporcao = (string) ($def['proporcao'] ?? '1:1');

        if ($this->ehCarrossel()) {
            $min = max(2, $kinds->cartoes($tipo)['min']);
            $this->slides = array_fill(0, $min, ['titulo' => '', 'texto' => '']);
        }

        $slug = (string) request()->query('nota', '');
        if ($slug !== '') {
            $this->carregarNota($vault, $slug);
        }
    }

    /** Pré-preenche a oficina com uma publicação já gravada. */
    private function carregarNota(VaultContract $vault, string $slug): void
    {
        $nota = $vault->get('rascunhos/'.$slug.'.md');
        if (! $nota) {
            return;
        }

        $this->notaPath = $nota->path;
        $this->titulo = (string) $nota->get('titulo', '');
        $this->plataforma = (string) $nota->get('plataforma', $this->plataforma);
        $this->proporcao = (string) $nota->get('proporcao', $this->proporcao);
        $this->img = array_values((array) $nota->get('imagens', []));
        $this->hist = (array) $nota->get('imagens_hist', []);
        $this->referencias = array_values((array) $nota->get('referencias', []));

        if ($this->ehCarrossel()) {
            $slides = $this->slidesDoCorpo($nota->body);
            $this->slides = $slides !== [] ? $slides : $this->slides;
        } else {
            $this->legenda = $nota->body;
        }
    }

    /** Reconstrói os cartões a partir do corpo Markdown («## Título» + «---»). */
    private function slidesDoCorpo(string $body): array
    {
        $slides = [];
        foreach (preg_split('/\n\n---\n\n/', trim($body)) ?: [] as $bloco) {
            $bloco = trim($bloco);
            if ($bloco === '') {
                continue;
            }
            if (preg_match('/^##\s*(.+?)(?:\n(.*))?$/s', $bloco, $m)) {
                $slides[] = ['titulo' => trim($m[1]), 'texto' => trim($m[2] ?? '')];
            } else {
                $slides[] = ['titulo' => '', 'texto' => $bloco];
            }
        }

        return $slides;
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

    /** Número de cartões: carrossel = nº de slides; peça única = 1. */
    public function numCartoes(): int
    {
        return $this->ehCarrossel() ? count($this->slides) : 1;
    }

    /** Título/texto de um cartão pelo índice. @return array{titulo:string,texto:string} */
    private function dadosCartao(int $i): array
    {
        if ($this->ehCarrossel()) {
            return [
                'titulo' => trim((string) ($this->slides[$i]['titulo'] ?? '')),
                'texto' => trim((string) ($this->slides[$i]['texto'] ?? '')),
            ];
        }

        return ['titulo' => $this->titulo !== '' ? $this->titulo : 'Peça', 'texto' => $this->legenda];
    }

    // ---------------------------------------------------------------- cartões

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
        if (count($this->slides) <= $min) {
            return;
        }

        unset($this->slides[$i]);
        $this->slides = array_values($this->slides);

        // Reindexa os mapas de imagem para acompanhar os índices dos cartões.
        foreach (['img', 'editar', 'hist', 'gerando'] as $prop) {
            $arr = $this->{$prop};
            unset($arr[$i]);
            $novo = [];
            foreach ($arr as $k => $v) {
                $novo[$k > $i ? $k - 1 : $k] = $v;
            }
            $this->{$prop} = $novo;
        }
    }

    // ------------------------------------------------- imagens de referência

    /** Guarda os ficheiros carregados como referências (com descrição a preencher). */
    public function updatedUploads(): void
    {
        $this->validate(['uploads.*' => 'image|max:8192'], [], ['uploads.*' => 'imagem']);

        $dir = public_path('media/publicacoes/refs');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        foreach ($this->uploads as $file) {
            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $nome = (string) Str::uuid().'.'.$ext;
            copy($file->getRealPath(), $dir.'/'.$nome);
            $this->referencias[] = ['path' => 'media/publicacoes/refs/'.$nome, 'descricao' => ''];
        }

        $this->uploads = [];
    }

    public function removerReferencia(int $i): void
    {
        unset($this->referencias[$i]);
        $this->referencias = array_values($this->referencias);
    }

    /** Descrições não vazias das referências (contexto para a redação). @return array<int,string> */
    private function refDescricoes(): array
    {
        return array_values(array_filter(array_map(
            fn ($r) => trim((string) ($r['descricao'] ?? '')),
            $this->referencias,
        )));
    }

    /** Caminhos das imagens de referência (para o gerador de imagens). @return array<int,string> */
    private function refPaths(): array
    {
        return array_values(array_map(fn ($r) => (string) ($r['path'] ?? ''), $this->referencias));
    }

    // ------------------------------------------------------------- redação IA

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

        PlanearPublicacaoJob::dispatch($this->tipo, $this->brief, $this->plataforma, $this->planToken, $this->refDescricoes());

        $this->verificarPlano();
    }

    public function verificarPlano(): void
    {
        if (! $this->aRedigir || $this->planToken === null) {
            return;
        }

        $r = Cache::get(PlanearPublicacaoJob::key($this->planToken));
        if ($r === null) {
            return;
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
            : 'IA indisponível neste contexto — gerei um rascunho local. Para redação forte, corra «php artisan queue:work» num terminal com a sua sessão do Claude.';
    }

    public function cancelarRedacao(): void
    {
        $this->aRedigir = false;
        $this->planToken = null;
        $this->aviso = null;
    }

    // ------------------------------------------------------------- imagens

    /** Gera (ou regenera) TODAS as imagens de uma vez, com consistência visual. */
    public function gerarImagens(): void
    {
        $plano = $this->planoAtual();
        if ($plano->slides === []) {
            $this->addError('slides', 'Componha a peça antes de gerar imagens.');

            return;
        }

        // As imagens actuais passam a histórico.
        foreach ($this->img as $i => $atual) {
            $this->empurrarHistorico($i, $atual);
        }

        $this->imgToken = (string) Str::uuid();
        $this->aGerar = true;

        // Se a peça já está guardada, marca-a «a gerar» para o painel a mostrar.
        $slug = $this->notaPath !== null ? pathinfo($this->notaPath, PATHINFO_FILENAME) : '';
        if ($slug !== '') {
            Cache::put(GerarImagensJob::notaKey($slug), true, now()->addMinutes(15));
        }

        GerarImagensJob::dispatch(
            $this->tipo, $this->titulo, $this->plataforma, $this->legenda, $this->slides, $this->imgToken, $this->proporcao, $this->refPaths(), $slug,
        )->onQueue('media');

        $this->verificarImagens();
    }

    /** Regenera UM cartão. Com instrução + imagem actual → edição imagem→imagem. */
    public function regenerarCartao(int $i): void
    {
        $dados = $this->dadosCartao($i);
        if ($dados['titulo'] === '' && $dados['texto'] === '') {
            return;
        }

        $atual = $this->img[$i] ?? null;
        if ($atual !== null) {
            $this->empurrarHistorico($i, $atual);
        }

        $token = (string) Str::uuid();
        $this->gerando[$i] = $token;

        RegenerarCartaoJob::dispatch(
            $this->tipo, $this->plataforma, $i, $dados['titulo'], $dados['texto'],
            (string) ($this->editar[$i] ?? ''), $atual, $i + 1, $this->numCartoes(), $token, $this->proporcao, $this->refPaths(),
        )->onQueue('media');

        $this->verificarImagens();
    }

    /** Sondado por wire:poll: aplica imagens prontas (lote e por cartão). */
    public function verificarImagens(): void
    {
        // Lote (gerar todas).
        if ($this->aGerar && $this->imgToken !== null) {
            $r = Cache::get(GerarImagensJob::key($this->imgToken));
            if ($r !== null) {
                $this->aGerar = false;
                Cache::forget(GerarImagensJob::key($this->imgToken));
                if (empty($r['erro'])) {
                    foreach (($r['imagens'] ?? []) as $i => $path) {
                        $this->img[$i] = $path;
                    }
                } else {
                    $this->addError('slides', 'O desenho das imagens falhou. Confirme o worker e a chave do kie.ai.');
                }
            }
        }

        // Por cartão.
        foreach ($this->gerando as $i => $token) {
            $r = Cache::get(RegenerarCartaoJob::key($token));
            if ($r === null) {
                continue;
            }
            Cache::forget(RegenerarCartaoJob::key($token));
            unset($this->gerando[$i]);

            if (empty($r['erro'])) {
                $this->img[$i] = (string) $r['imagem'];
                $this->editar[$i] = '';
            } else {
                $this->addError('slides', 'A regeneração do cartão '.($i + 1).' falhou.');
            }
        }
    }

    /** Restaura uma versão anterior de um cartão (troca com a actual). */
    public function restaurarVersao(int $i, string $path): void
    {
        $atual = $this->img[$i] ?? null;
        // Remove a versão escolhida do histórico e coloca a actual lá.
        $this->hist[$i] = array_values(array_filter($this->hist[$i] ?? [], fn ($p) => $p !== $path));
        if ($atual !== null && $atual !== $path) {
            array_unshift($this->hist[$i], $atual);
        }
        $this->img[$i] = $path;
    }

    private function empurrarHistorico(int $i, ?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }
        $this->hist[$i] = $this->hist[$i] ?? [];
        if (! in_array($path, $this->hist[$i], true)) {
            array_unshift($this->hist[$i], $path);
            $this->hist[$i] = array_slice($this->hist[$i], 0, 8); // limita o histórico
        }
    }

    public function cancelarImagens(): void
    {
        $this->aGerar = false;
        $this->imgToken = null;
        $this->gerando = [];
    }

    // ------------------------------------------------------------- guardar

    public function criarRascunho(VaultContract $vault): void
    {
        $this->validate($this->rules());

        $plano = $this->planoAtual();
        if ($this->ehCarrossel() && count($plano->slides) < 2) {
            $this->addError('slides', 'Um carrossel precisa de pelo menos 2 cartões com texto.');

            return;
        }

        $formato = $this->kinds()->formato($this->tipo);

        $frontmatter = [
            'titulo' => $this->titulo,
            'tipo' => $this->tipo,
            'formato' => $formato,
            'gabarito' => (string) ($this->kind['gabarito'] ?? ''),
            'plataforma' => $this->plataforma,
            'proporcao' => $this->proporcao,
            'origem' => 'publicacoes/oficina',
            'cartoes' => count($plano->slides),
            'tags' => array_values(array_unique([$this->tipo, $this->plataforma])),
        ];
        if ($this->img !== []) {
            ksort($this->img);
            $frontmatter['imagens'] = array_values($this->img);
            $frontmatter['imagens_hist'] = $this->hist;
        }
        if ($this->referencias !== []) {
            $frontmatter['referencias'] = array_values($this->referencias);
        }

        $body = $plano->toBody($formato);

        if ($this->notaPath !== null) {
            $existente = $vault->get($this->notaPath);
            $frontmatter = array_merge(
                (array) ($existente?->frontmatter ?? []),
                $frontmatter,
                ['estado' => $existente?->get('estado', 'rascunho')],
            );
            $nota = $vault->put($this->notaPath, $frontmatter, $body);
            $this->guardado = $nota->title();

            return;
        }

        $frontmatter['estado'] = 'rascunho';
        $nota = $vault->create('rascunhos', $frontmatter, $body);

        $this->guardado = $nota->title();
        $this->reset('brief', 'img', 'hist', 'editar', 'gerando', 'referencias');
        if ($this->ehCarrossel()) {
            $this->slides = array_fill(0, max(2, $this->kinds()->cartoes($this->tipo)['min']), ['titulo' => '', 'texto' => '']);
            $this->titulo = '';
        } else {
            $this->reset('titulo', 'legenda');
        }
    }

    public function remover(VaultContract $vault): void
    {
        if ($this->notaPath !== null) {
            $vault->delete($this->notaPath);
            $this->redirect(route('publicacoes'), navigate: true);
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
