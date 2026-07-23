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

    /** @var array<int,array{path:string,descricao:string}> imagens de referência (pool) */
    public array $referencias = [];

    /** @var array<int,array<int,string>> imagens anexas por cartão (caminhos web) */
    public array $anexos = [];

    /** @var array<int,string> prompt kie por cartão (mostrado/editável na oficina) */
    public array $prompts = [];

    /** @var array<int,bool> cartões cujo prompt foi editado à mão (confirma antes de regenerar) */
    public array $promptEditado = [];

    /** @var array<int,mixed> ficheiros a carregar directamente num cartão (input) */
    public array $cartaoUploads = [];

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
        $this->anexos = (array) $nota->get('anexos', []);
        $this->prompts = (array) $nota->get('prompts', []);

        if ($this->ehCarrossel()) {
            $slides = $this->slidesDoCorpo($nota->body);
            $this->slides = $slides !== [] ? $slides : $this->slides;
        } else {
            $this->legenda = $nota->body;
        }

        // Se esta peça está a gerar imagens (noutra sessão/aba ou antes de sair),
        // retoma o estado «a gerar» — a view volta a sondar e recarrega quando terminar.
        if (Cache::get(GerarImagensJob::notaKey($slug))) {
            $this->aGerar = true;
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

        // Reindexa os mapas por cartão para acompanhar os índices dos cartões.
        foreach (['img', 'editar', 'hist', 'gerando', 'anexos', 'prompts', 'promptEditado'] as $prop) {
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

        foreach ($this->uploads as $file) {
            $this->referencias[] = ['path' => $this->guardarUpload($file), 'descricao' => ''];
        }

        $this->uploads = [];
    }

    /** Copia um ficheiro carregado para a pasta de referências; devolve o caminho web. */
    private function guardarUpload($file): string
    {
        $dir = public_path('media/publicacoes/refs');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
        $nome = (string) Str::uuid().'.'.$ext;
        copy($file->getRealPath(), $dir.'/'.$nome);

        return 'media/publicacoes/refs/'.$nome;
    }

    public function removerReferencia(int $i): void
    {
        $path = (string) ($this->referencias[$i]['path'] ?? '');
        unset($this->referencias[$i]);
        $this->referencias = array_values($this->referencias);

        // Solta a referência de quaisquer cartões onde estivesse anexa.
        if ($path !== '') {
            foreach (array_keys($this->anexos) as $c) {
                $this->desanexar((int) $c, $path);
            }
        }
    }

    /** Pool de referências indexada (índice + descrição) para a IA atribuir. @return array<int,array{indice:int,descricao:string}> */
    private function refsIndexadas(): array
    {
        $out = [];
        foreach ($this->referencias as $k => $r) {
            $out[] = ['indice' => (int) $k, 'descricao' => trim((string) ($r['descricao'] ?? ''))];
        }

        return $out;
    }

    /** Caminhos das imagens de referência (toda a pool). @return array<int,string> */
    private function refPaths(): array
    {
        return array_values(array_map(fn ($r) => (string) ($r['path'] ?? ''), $this->referencias));
    }

    /**
     * Referências GERAIS: imagens da pool que NÃO estão anexadas a nenhum cartão
     * específico. Uma imagem anexada a um cartão vai só a esse cartão (via anexos)
     * — não é aplicada a toda a peça. As não-anexadas (ex.: logótipo) valem para
     * todos os cartões, como antes.
     *
     * @return array<int,string>
     */
    private function refsGlobais(): array
    {
        $anexadas = [];
        foreach ($this->anexos as $lista) {
            foreach ((array) $lista as $p) {
                $anexadas[(string) $p] = true;
            }
        }

        return array_values(array_filter($this->refPaths(), fn ($p) => $p !== '' && ! isset($anexadas[$p])));
    }

    // -------------------------------------------------- anexos por cartão

    /** Liga/desliga uma imagem da pool a um cartão. */
    public function alternarAnexo(int $i, int $poolIndex): void
    {
        $path = (string) ($this->referencias[$poolIndex]['path'] ?? '');
        if ($path === '') {
            return;
        }

        $atual = $this->anexosDoCartao($i);
        $this->anexos[$i] = in_array($path, $atual, true)
            ? array_values(array_filter($atual, fn ($p) => $p !== $path))
            : array_merge($atual, [$path]);

        $this->invalidarPrompt($i);
    }

    /** Remove um anexo de um cartão (pela miniatura). */
    public function desanexar(int $i, string $path): void
    {
        $this->anexos[$i] = array_values(array_filter($this->anexosDoCartao($i), fn ($p) => $p !== $path));
        $this->invalidarPrompt($i);
    }

    /** Upload directo num cartão: junta à pool E anexa ao cartão. */
    public function updatedCartaoUploads($value, $key): void
    {
        $i = (int) $key;
        $files = array_filter(is_array($value) ? $value : [$value]);

        foreach ($files as $file) {
            \Illuminate\Support\Facades\Validator::make(
                ['f' => $file], ['f' => 'image|max:8192'], [], ['f' => 'imagem'],
            )->validate();

            $path = $this->guardarUpload($file);
            $this->referencias[] = ['path' => $path, 'descricao' => ''];
            $this->anexos[$i] = array_merge($this->anexosDoCartao($i), [$path]);
        }

        unset($this->cartaoUploads[$key]);
        $this->invalidarPrompt($i);
    }

    /** Caminhos das imagens anexas a um cartão. @return array<int,string> */
    private function anexosDoCartao(int $i): array
    {
        return array_values(array_filter((array) ($this->anexos[$i] ?? [])));
    }

    /** Descrições das imagens anexas a um cartão (via pool). @return array<int,string> */
    private function anexosDescrDoCartao(int $i): array
    {
        return array_values(array_filter(array_map(
            fn ($p) => $this->descricaoDaRef((string) $p),
            $this->anexosDoCartao($i),
        )));
    }

    /** Descrição de uma referência pelo caminho (ou '' se não encontrada). */
    private function descricaoDaRef(string $path): string
    {
        foreach ($this->referencias as $r) {
            if (($r['path'] ?? '') === $path) {
                return trim((string) ($r['descricao'] ?? ''));
            }
        }

        return '';
    }

    /** Mapa cartão→caminhos anexos, só cartões com anexos. @return array<int,array<int,string>> */
    private function anexosParaJob(): array
    {
        $out = [];
        foreach (array_keys($this->anexos) as $i) {
            $paths = $this->anexosDoCartao((int) $i);
            if ($paths !== []) {
                $out[(int) $i] = $paths;
            }
        }

        return $out;
    }

    /** Mapa cartão→descrições anexas, só cartões com anexos. @return array<int,array<int,string>> */
    private function anexosDescrParaJob(): array
    {
        $out = [];
        foreach (array_keys($this->anexos) as $i) {
            $d = $this->anexosDescrDoCartao((int) $i);
            if ($d !== []) {
                $out[(int) $i] = $d;
            }
        }

        return $out;
    }

    // ------------------------------------------------ prompt kie por cartão

    /** (Re)compõe o prompt kie de um cartão a partir do estado actual. */
    public function montarPrompt(int $i): string
    {
        $dados = $this->dadosCartao($i);
        $slide = new \App\Services\Publicacoes\Dto\SlidePlano($i + 1, $dados['titulo'], $dados['texto']);

        $anteriores = [];
        if ($this->ehCarrossel()) {
            for ($k = 0; $k < $i; $k++) {
                $t = trim((string) ($this->slides[$k]['titulo'] ?? ''));
                if ($t !== '') {
                    $anteriores[] = $t;
                }
            }
        }

        return app(\App\Services\Publicacoes\Rendering\KiePromptComposer::class)->paraCartao($slide, [
            'proporcao' => $this->proporcao,
            'capa' => $this->ehCarrossel() && $i === 0,
            'ordem' => $i + 1,
            'total' => $this->numCartoes(),
            'postTitulo' => $this->titulo,
            'anteriores' => $anteriores,
            'anexos' => $this->anexosDescrDoCartao($i),
        ]);
    }

    /** Botão «Regenerar prompt»: recompõe e limpa a marca de edição manual. */
    public function regenerarPrompt(int $i): void
    {
        $this->prompts[$i] = $this->montarPrompt($i);
        $this->promptEditado[$i] = false;
    }

    /** Marca um prompt como editado à mão (para confirmar antes de regenerar). */
    public function updatedPrompts($value, $key): void
    {
        $this->promptEditado[(int) $key] = true;
    }

    /** Texto ou anexos mudaram: recompõe o prompt do cartão se não foi editado à mão. */
    private function invalidarPrompt(int $i): void
    {
        if (! ($this->promptEditado[$i] ?? false)) {
            $this->prompts[$i] = $this->montarPrompt($i);
        }
    }

    /** O título/legenda mudou: recompõe os prompts não editados (título alimenta o contexto). */
    public function updatedTitulo(): void
    {
        for ($i = 0; $i < $this->numCartoes(); $i++) {
            $this->invalidarPrompt($i);
        }
    }

    public function updatedLegenda(): void
    {
        if (! $this->ehCarrossel()) {
            $this->invalidarPrompt(0);
        }
    }

    public function updatedSlides($value, $key): void
    {
        $this->invalidarPrompt((int) explode('.', (string) $key)[0]);
    }

    /** Garante um prompt para cada cartão (compõe os em falta). Antes de gerar. */
    private function garantirPrompts(): void
    {
        for ($i = 0; $i < $this->numCartoes(); $i++) {
            if (trim((string) ($this->prompts[$i] ?? '')) === '') {
                $this->prompts[$i] = $this->montarPrompt($i);
            }
        }
    }

    /**
     * Semeia os anexos por cartão a partir dos índices de referência que a IA
     * atribuiu a cada slide do plano (campo «referencias»).
     *
     * @param  array<int,array{referencias?:array<int,int>}>  $slides
     */
    private function semearAnexosDoPlano(array $slides): void
    {
        foreach (array_values($slides) as $i => $s) {
            $indices = is_array($s['referencias'] ?? null) ? $s['referencias'] : [];
            $paths = [];
            foreach ($indices as $idx) {
                $p = (string) ($this->referencias[(int) $idx]['path'] ?? '');
                if ($p !== '') {
                    $paths[] = $p;
                }
            }
            if ($paths !== []) {
                $this->anexos[$i] = array_values(array_unique($paths));
            }
        }
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
        $this->dispatch('loader-show', message: 'A IATECA está a redigir a publicação…');

        PlanearPublicacaoJob::dispatch($this->tipo, $this->brief, $this->plataforma, $this->planToken, $this->refsIndexadas());

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
        $this->dispatch('loader-hide');
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

        // A IA pode ter atribuído referências a cada cartão → semeia os anexos e
        // recompõe os prompts com o conteúdo/anexos frescos.
        $this->semearAnexosDoPlano($r['slides'] ?? []);
        $this->prompts = [];
        $this->promptEditado = [];
        $this->garantirPrompts();

        $this->aviso = ($r['fonte'] ?? null) === 'ia'
            ? 'Redigido pela IA ('.($r['fornecedor'] ?: 'LLM').'). Reveja e ajuste antes de guardar.'
            : 'IA indisponível neste contexto — gerei um rascunho local. Para redação forte, corra «php artisan queue:work» num terminal com a sua sessão do Claude.';
    }

    public function cancelarRedacao(): void
    {
        $this->aRedigir = false;
        $this->planToken = null;
        $this->aviso = null;
        $this->dispatch('loader-hide');
    }

    // ------------------------------------------------------------- imagens

    /** Gera (ou regenera) TODAS as imagens de uma vez, com consistência visual. */
    public function gerarImagens(VaultContract $vault): void
    {
        $plano = $this->planoAtual();
        if ($plano->slides === []) {
            $this->addError('slides', 'Componha a peça antes de gerar imagens.');

            return;
        }

        // Grava a peça (se ainda não estava) ANTES de gerar, para que o trabalho
        // seja recuperável como os vídeos: fica em Rascunhos e as imagens gravam-se
        // na nota, mesmo que o utilizador saia da página.
        if ($this->notaPath === null) {
            $this->notaPath = $this->persistir($vault, $plano)->path;
        }

        // As imagens actuais passam a histórico.
        foreach ($this->img as $i => $atual) {
            $this->empurrarHistorico($i, $atual);
        }

        // Garante que cada cartão tem um prompt (o que se envia = o que se mostra).
        $this->garantirPrompts();

        $this->imgToken = (string) Str::uuid();
        $this->aGerar = true;
        $this->dispatch('loader-show', message: 'A desenhar os cartões com o kie.ai…');

        // Marca a peça «a gerar»: alimenta o painel e permite retomar o estado
        // ao voltar à página (a sondagem passa a ser pela nota, não pelo token).
        $slug = pathinfo($this->notaPath, PATHINFO_FILENAME);
        Cache::put(GerarImagensJob::notaKey($slug), true, now()->addMinutes(15));

        GerarImagensJob::dispatch(
            $this->tipo, $this->titulo, $this->plataforma, $this->legenda, $this->slides, $this->imgToken, $this->proporcao, $this->refsGlobais(), $slug,
            $this->prompts, $this->anexosParaJob(), $this->anexosDescrParaJob(),
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

        $instrucao = (string) ($this->editar[$i] ?? '');
        // Composição de raiz (sem instrução de edição) usa o prompt do cartão.
        if (trim($instrucao) === '' && trim((string) ($this->prompts[$i] ?? '')) === '') {
            $this->prompts[$i] = $this->montarPrompt($i);
        }

        $token = (string) Str::uuid();
        $this->gerando[$i] = $token;

        RegenerarCartaoJob::dispatch(
            $this->tipo, $this->plataforma, $i, $dados['titulo'], $dados['texto'],
            $instrucao, $atual, $i + 1, $this->numCartoes(), $token, $this->proporcao, $this->refsGlobais(),
            (string) ($this->prompts[$i] ?? ''), $this->anexosDoCartao($i), $this->anexosDescrDoCartao($i),
        )->onQueue('media');

        $this->verificarImagens();
    }

    /** Sondado por wire:poll: aplica imagens prontas (lote e por cartão). */
    public function verificarImagens(): void
    {
        // Lote gerado NESTA sessão (temos o token em cache).
        if ($this->aGerar && $this->imgToken !== null) {
            $r = Cache::get(GerarImagensJob::key($this->imgToken));
            if ($r !== null) {
                $this->aGerar = false;
                $this->dispatch('loader-hide');
                Cache::forget(GerarImagensJob::key($this->imgToken));
                if (empty($r['erro'])) {
                    foreach (($r['imagens'] ?? []) as $i => $path) {
                        $this->img[$i] = $path;
                    }
                } else {
                    $this->addError('slides', 'O desenho das imagens falhou'
                        .(! empty($r['msg']) ? ': '.$r['msg'] : '. Confirme o worker (fila «media») e os créditos do kie.ai.'));
                }
            }
        }
        // Lote RETOMADO — voltámos à peça e o token perdeu-se. Sonda a flag da
        // nota; quando a geração termina, recarrega as imagens gravadas na nota.
        elseif ($this->aGerar && $this->imgToken === null && $this->notaPath !== null) {
            $slug = pathinfo($this->notaPath, PATHINFO_FILENAME);
            if (! Cache::get(GerarImagensJob::notaKey($slug))) {
                $this->aGerar = false;
                $this->dispatch('loader-hide');
                if ($nota = app(VaultContract::class)->get($this->notaPath)) {
                    $this->img = array_values((array) $nota->get('imagens', []));
                    $this->hist = (array) $nota->get('imagens_hist', $this->hist);
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
        $this->dispatch('loader-hide');
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

        $eraNovo = $this->notaPath === null;
        $nota = $this->persistir($vault, $plano);
        $this->guardado = $nota->title();

        // Guardar uma peça NOVA limpa a oficina (compor a próxima); editar uma
        // peça já existente mantém-na aberta.
        if ($eraNovo) {
            $this->reset('brief', 'img', 'hist', 'editar', 'gerando', 'referencias', 'anexos', 'prompts', 'promptEditado');
            if ($this->ehCarrossel()) {
                $this->slides = array_fill(0, max(2, $this->kinds()->cartoes($this->tipo)['min']), ['titulo' => '', 'texto' => '']);
                $this->titulo = '';
            } else {
                $this->reset('titulo', 'legenda');
            }
        }
    }

    /**
     * Grava (ou atualiza) a peça no vault e devolve a nota, sem efeitos na UI.
     * Usado por «Guardar» e pela gravação automática antes de gerar imagens.
     * Não altera $this->notaPath — cabe a quem chama decidir se a peça fica «aberta».
     */
    private function persistir(VaultContract $vault, PublicacaoPlan $plano): \App\Services\Vault\VaultNote
    {
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
        if ($this->anexosParaJob() !== []) {
            $frontmatter['anexos'] = $this->anexosParaJob();
        }
        $promptsGuardar = array_filter($this->prompts, fn ($p) => trim((string) $p) !== '');
        if ($promptsGuardar !== []) {
            $frontmatter['prompts'] = $promptsGuardar;
        }

        $body = $plano->toBody($formato);

        if ($this->notaPath !== null) {
            $existente = $vault->get($this->notaPath);
            $frontmatter = array_merge(
                (array) ($existente?->frontmatter ?? []),
                $frontmatter,
                ['estado' => $existente?->get('estado', 'rascunho')],
            );

            return $vault->put($this->notaPath, $frontmatter, $body);
        }

        $frontmatter['estado'] = 'rascunho';

        return $vault->create('rascunhos', $frontmatter, $body);
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
