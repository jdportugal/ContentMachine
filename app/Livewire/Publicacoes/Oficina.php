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
 * Generic post workshop: composes, plans with AI and renders to image
 * any TYPE from the registry (config publicacoes.tipos). Each card has its
 * image beside it, with regeneration/editing by instruction and version history.
 */
#[Layout('components.layouts.app')]
class Oficina extends Component
{
    use WithFileUploads;

    public string $tipo = '';

    public string $titulo = '';

    public string $plataforma = 'instagram';

    /** Chosen resolution (ratio) — defaulted by type. */
    public string $proporcao = '1:1';

    public string $brief = '';

    public string $legenda = '';

    /** @var array files to upload (input) */
    public array $uploads = [];

    /** @var array<int,array{path:string,descricao:string}> reference images (pool) */
    public array $referencias = [];

    /** @var array<int,array<int,string>> images attached per card (web paths) */
    public array $anexos = [];

    /** @var array<int,string> kie prompt per card (shown/editable in the workshop) */
    public array $prompts = [];

    /** @var array<int,bool> cards whose prompt was edited by hand (confirm before regenerating) */
    public array $promptEditado = [];

    /** @var array<int,mixed> files to upload directly into a card (input) */
    public array $cartaoUploads = [];

    /** @var array<int,array{titulo:string,texto:string}> cards (carousel) */
    public array $slides = [];

    // --- Images per card (index 0..N-1; single piece uses index 0) ---
    /** @var array<int,string> current image (web path) per card */
    public array $img = [];

    /** @var array<int,string> edit instruction per card */
    public array $editar = [];

    /** @var array<int,array<int,string>> version history per card (recent first) */
    public array $hist = [];

    /** @var array<int,string> in-progress regeneration token per card */
    public array $gerando = [];

    public ?string $guardado = null;

    public ?string $aviso = null;

    /** Writing in progress. */
    public ?string $planToken = null;

    public bool $aRedigir = false;

    /** Rendering of ALL images in progress. */
    public ?string $imgToken = null;

    public bool $aGerar = false;

    /** Path of the note being edited (null = new post). */
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

            return;
        }

        // Seeded from a long video ("Generate post" in Clips).
        if (($seed = session('oficina_brief')) !== null) {
            $this->brief = (string) $seed;
            session()->forget('oficina_brief');
        }
    }

    /** Pre-fills the workshop with an already saved post. */
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

        // If this piece is generating images (in another session/tab or before leaving),
        // resume the "generating" state — the view polls again and reloads when it finishes.
        if (Cache::get(GerarImagensJob::notaKey($slug))) {
            $this->aGerar = true;
        }
    }

    /** Rebuilds the cards from the Markdown body ("## Title" + "---"). */
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

    /** Number of cards: carousel = number of slides; single piece = 1. */
    public function numCartoes(): int
    {
        return $this->ehCarrossel() ? count($this->slides) : 1;
    }

    /** Title/text of a card by index. @return array{titulo:string,texto:string} */
    private function dadosCartao(int $i): array
    {
        if ($this->ehCarrossel()) {
            return [
                'titulo' => trim((string) ($this->slides[$i]['titulo'] ?? '')),
                'texto' => trim((string) ($this->slides[$i]['texto'] ?? '')),
            ];
        }

        return ['titulo' => $this->titulo !== '' ? $this->titulo : 'Piece', 'texto' => $this->legenda];
    }

    // ------------------------------------------------------------------ cards

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

        // Reindex the per-card maps to follow the card indices.
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

    // -------------------------------------------------- reference images

    /** Saves the uploaded files as references (with description to fill in). */
    public function updatedUploads(): void
    {
        $this->validate(['uploads.*' => 'image|max:8192'], [], ['uploads.*' => 'image']);

        foreach ($this->uploads as $file) {
            $this->referencias[] = ['path' => $this->guardarUpload($file), 'descricao' => ''];
        }

        $this->uploads = [];
    }

    /** Copies an uploaded file to the references folder; returns the web path. */
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

        // Detach the reference from any cards where it was attached.
        if ($path !== '') {
            foreach (array_keys($this->anexos) as $c) {
                $this->desanexar((int) $c, $path);
            }
        }
    }

    /** Indexed reference pool (index + description) for the AI to assign. @return array<int,array{indice:int,descricao:string}> */
    private function refsIndexadas(): array
    {
        $out = [];
        foreach ($this->referencias as $k => $r) {
            $out[] = ['indice' => (int) $k, 'descricao' => trim((string) ($r['descricao'] ?? ''))];
        }

        return $out;
    }

    /** Paths of the reference images (the whole pool). @return array<int,string> */
    private function refPaths(): array
    {
        return array_values(array_map(fn ($r) => (string) ($r['path'] ?? ''), $this->referencias));
    }

    /**
     * GLOBAL references: pool images that are NOT attached to any specific
     * card. An image attached to a card goes only to that card (via anexos)
     * — it is not applied to the whole piece. Unattached ones (e.g. logo) apply to
     * all cards, as before.
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

    // -------------------------------------------------- attachments per card

    /** Toggles a pool image on/off for a card. */
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

    /** Removes an attachment from a card (via the thumbnail). */
    public function desanexar(int $i, string $path): void
    {
        $this->anexos[$i] = array_values(array_filter($this->anexosDoCartao($i), fn ($p) => $p !== $path));
        $this->invalidarPrompt($i);
    }

    /** Direct upload into a card: adds to the pool AND attaches to the card. */
    public function updatedCartaoUploads($value, $key): void
    {
        $i = (int) $key;
        $files = array_filter(is_array($value) ? $value : [$value]);

        foreach ($files as $file) {
            \Illuminate\Support\Facades\Validator::make(
                ['f' => $file], ['f' => 'image|max:8192'], [], ['f' => 'image'],
            )->validate();

            $path = $this->guardarUpload($file);
            $this->referencias[] = ['path' => $path, 'descricao' => ''];
            $this->anexos[$i] = array_merge($this->anexosDoCartao($i), [$path]);
        }

        unset($this->cartaoUploads[$key]);
        $this->invalidarPrompt($i);
    }

    /** Paths of the images attached to a card. @return array<int,string> */
    private function anexosDoCartao(int $i): array
    {
        return array_values(array_filter((array) ($this->anexos[$i] ?? [])));
    }

    /** Descriptions of the images attached to a card (via the pool). @return array<int,string> */
    private function anexosDescrDoCartao(int $i): array
    {
        return array_values(array_filter(array_map(
            fn ($p) => $this->descricaoDaRef((string) $p),
            $this->anexosDoCartao($i),
        )));
    }

    /** Description of a reference by path (or '' if not found). */
    private function descricaoDaRef(string $path): string
    {
        foreach ($this->referencias as $r) {
            if (($r['path'] ?? '') === $path) {
                return trim((string) ($r['descricao'] ?? ''));
            }
        }

        return '';
    }

    /** Map card→attached paths, only cards with attachments. @return array<int,array<int,string>> */
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

    /** Map card→attached descriptions, only cards with attachments. @return array<int,array<int,string>> */
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

    // ------------------------------------------------ kie prompt per card

    /** (Re)composes a card's kie prompt from the current state. */
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

    /** "Regenerate prompt" button: recomposes and clears the manual-edit mark. */
    public function regenerarPrompt(int $i): void
    {
        $this->prompts[$i] = $this->montarPrompt($i);
        $this->promptEditado[$i] = false;
    }

    /** Marks a prompt as hand-edited (to confirm before regenerating). */
    public function updatedPrompts($value, $key): void
    {
        $this->promptEditado[(int) $key] = true;
    }

    /** Text or attachments changed: recomposes the card's prompt if not hand-edited. */
    private function invalidarPrompt(int $i): void
    {
        if (! ($this->promptEditado[$i] ?? false)) {
            $this->prompts[$i] = $this->montarPrompt($i);
        }
    }

    /** The title/caption changed: recomposes the non-edited prompts (title feeds the context). */
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

    /** Ensures a prompt for each card (composes the missing ones). Before generating. */
    private function garantirPrompts(): void
    {
        for ($i = 0; $i < $this->numCartoes(); $i++) {
            if (trim((string) ($this->prompts[$i] ?? '')) === '') {
                $this->prompts[$i] = $this->montarPrompt($i);
            }
        }
    }

    /**
     * Seeds the per-card attachments from the reference indices that the AI
     * assigned to each plan slide (field "referencias").
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

    // ------------------------------------------------------------- AI writing

    public function redigirComIa(): void
    {
        $this->aviso = null;

        if (trim($this->brief) === '') {
            $this->addError('brief', 'Write a topic or brief for the AI to develop.');

            return;
        }

        $this->planToken = (string) Str::uuid();
        $this->aRedigir = true;
        $this->aviso = 'The AI is writing… (requires a worker: "php artisan queue:work").';
        $this->dispatch('loader-show', message: 'Brand Machine is writing the post…');

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
            $this->aviso = 'Writing failed. Check that the worker is running and try again.';

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

        // The AI may have assigned references to each card → seed the attachments and
        // recompose the prompts with the fresh content/attachments.
        $this->semearAnexosDoPlano($r['slides'] ?? []);
        $this->prompts = [];
        $this->promptEditado = [];
        $this->garantirPrompts();

        $this->aviso = ($r['fonte'] ?? null) === 'ia'
            ? 'Written by AI ('.($r['fornecedor'] ?: 'LLM').'). Review and adjust before saving.'
            : 'AI unavailable in this context — I generated a local draft. For strong writing, run "php artisan queue:work" in a terminal with your Claude session.';
    }

    public function cancelarRedacao(): void
    {
        $this->aRedigir = false;
        $this->planToken = null;
        $this->aviso = null;
        $this->dispatch('loader-hide');
    }

    // ------------------------------------------------------------- images

    /** Generates (or regenerates) ALL images at once, with visual consistency. */
    public function gerarImagens(VaultContract $vault): void
    {
        $plano = $this->planoAtual();
        if ($plano->slides === []) {
            $this->addError('slides', 'Compose the piece before generating images.');

            return;
        }

        // Save the piece (if not already) BEFORE generating, so the work
        // is recoverable like the videos: it stays in Drafts and the images are saved
        // in the note, even if the user leaves the page.
        if ($this->notaPath === null) {
            $this->notaPath = $this->persistir($vault, $plano)->path;
        }

        // The current images move to history.
        foreach ($this->img as $i => $atual) {
            $this->empurrarHistorico($i, $atual);
        }

        // Ensure each card has a prompt (what is sent = what is shown).
        $this->garantirPrompts();

        $this->imgToken = (string) Str::uuid();
        $this->aGerar = true;
        $this->dispatch('loader-show', message: 'Rendering the cards with kie.ai…');

        // Mark the piece as "generating": feeds the panel and allows resuming the state
        // when returning to the page (polling switches to the note, not the token).
        $slug = pathinfo($this->notaPath, PATHINFO_FILENAME);
        Cache::put(GerarImagensJob::notaKey($slug), true, now()->addMinutes(15));

        GerarImagensJob::dispatch(
            $this->tipo, $this->titulo, $this->plataforma, $this->legenda, $this->slides, $this->imgToken, $this->proporcao, $this->refsGlobais(), $slug,
            $this->prompts, $this->anexosParaJob(), $this->anexosDescrParaJob(),
        )->onQueue('media');

        $this->verificarImagens();
    }

    /** Regenerates ONE card. With instruction + current image → image→image editing. */
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
        // Composing from scratch (no edit instruction) uses the card's prompt.
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

    /** Polled by wire:poll: applies ready images (batch and per card). */
    public function verificarImagens(): void
    {
        // Batch generated in THIS session (we have the token in cache).
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
                    $this->addError('slides', 'Rendering the images failed'
                        .(! empty($r['msg']) ? ': '.$r['msg'] : '. Check the worker (queue "media") and your kie.ai credits.'));
                }
            }
        }
        // Batch RESUMED — we came back to the piece and the token was lost. Polls the note's
        // flag; when generation finishes, reloads the images saved in the note.
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

        // Per card.
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
                $this->addError('slides', 'Regenerating card '.($i + 1).' failed.');
            }
        }
    }

    /** Restores a previous version of a card (swaps with the current one). */
    public function restaurarVersao(int $i, string $path): void
    {
        $atual = $this->img[$i] ?? null;
        // Remove the chosen version from history and put the current one there.
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
            $this->hist[$i] = array_slice($this->hist[$i], 0, 8); // limit the history
        }
    }

    public function cancelarImagens(): void
    {
        $this->aGerar = false;
        $this->imgToken = null;
        $this->gerando = [];
        $this->dispatch('loader-hide');
    }

    // ------------------------------------------------------------- save

    public function criarRascunho(VaultContract $vault): void
    {
        $this->validate($this->rules());

        $plano = $this->planoAtual();
        if ($this->ehCarrossel() && count($plano->slides) < 2) {
            $this->addError('slides', 'A carousel needs at least 2 cards with text.');

            return;
        }

        $eraNovo = $this->notaPath === null;
        $nota = $this->persistir($vault, $plano);
        $this->guardado = $nota->title();

        // Saving a NEW piece clears the workshop (to compose the next one); editing an
        // existing piece keeps it open.
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
     * Saves (or updates) the piece in the vault and returns the note, without UI effects.
     * Used by "Save" and by the automatic save before generating images.
     * Does not change $this->notaPath — the caller decides whether the piece stays "open".
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

    /** Builds a plan from the current form state (without AI). */
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
            ->title(($this->kind['label'] ?? 'Post'));
    }
}
