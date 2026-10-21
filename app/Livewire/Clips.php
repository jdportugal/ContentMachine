<?php

namespace App\Livewire;

use App\Services\Content\FinishedContent;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Projects\ProjectLanguage;
use App\Services\Shorts\MusicLibrary;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Shorts Generator')]
class Clips extends Component
{
    use WithFileUploads;

    // Long video open (detail). Null → list of videos.
    public ?string $fonteAberta = null;

    // Show the new video form (revealed by button).
    public bool $mostrarNovaFonte = false;

    // "New source" form.
    public string $novaFonte = '';

    public string $novaFonteTitulo = '';

    /** Whisper's language hint for a new source — the project's language, not a fixed 'pt'. */
    public string $novaFonteLingua = '';

    // Long video uploaded via drag-and-drop (up to 2 GB — see config/livewire.php).
    public $novoVideo = null;

    // "Add clip" forms, by source slug.
    /** @var array<string,string> */
    public array $clipTitulo = [];

    /** @var array<string,string> */
    public array $clipInicio = [];

    /** @var array<string,string> */
    public array $clipFim = [];

    /** @var array<string,string> */
    public array $clipTags = [];

    // Caption editor for the open clip.
    public ?string $clipAberto = null;

    /** @var array<int,array<string,mixed>> */
    public array $segmentos = [];

    /** @var array<string,mixed> */
    public array $estilo = [];

    public string $modoPalavra = 'karaoke';

    // Editable details of the open clip (title, description, tags).
    public string $clipTituloEdit = '';

    public string $clipDescricao = '';

    public string $clipTagsEdit = '';

    // Music choice for the open clip: '' (random) | 'nenhuma' | track name.
    public string $musica = '';

    public float $musicaVolume = 0.1;

    /** How the open clip is framed on the next cut: fill | blur | original. */
    public string $enquadramento = 'original';

    // --- Sources (long videos) ---------------------------------------

    /** Sends a notification (toast) to the client. */
    private function notificar(string $texto, string $tipo = 'ok'): void
    {
        $this->dispatch('toast', message: $texto, type: $tipo);
    }

    public function alternarNovaFonte(): void
    {
        $this->mostrarNovaFonte = ! $this->mostrarNovaFonte;
    }

    public function adicionarFonte(ShortsPipeline $pipeline): void
    {
        // Accepts an uploaded file OR a path/URL — at least one.
        $this->validate([
            'novoVideo' => 'nullable|file|mimetypes:video/mp4,video/quicktime|max:2097152',
            'novaFonte' => 'nullable|string',
        ], [
            'novoVideo.mimetypes' => 'Unsupported video format. Use mp4 or mov.',
            'novoVideo.max' => 'The video is too large (maximum 2 GB).',
        ]);

        if ($this->novoVideo) {
            // Absolute path: LocalVideoEngine::resolveSource does is_file($ref).
            $ref = Storage::disk('local')->path($this->novoVideo->store('clips/uploads'));
            $titulo = trim($this->novaFonteTitulo) !== ''
                ? trim($this->novaFonteTitulo)
                : $this->novoVideo->getClientOriginalName();
        } elseif (trim($this->novaFonte) !== '') {
            $ref = trim($this->novaFonte);
            $titulo = trim($this->novaFonteTitulo);
        } else {
            $this->addError('novaFonte', 'Drag a video or provide a path/URL.');

            return;
        }

        $fonte = $pipeline->criarFonte($ref, $titulo, $this->novaFonteLingua);

        $this->reset('novaFonte', 'novaFonteTitulo', 'novoVideo');
        $this->mostrarNovaFonte = false;
        $this->notificar('Video added.');

        // Opens the video right away to continue (transcribe → choose clips).
        $this->abrirFonte($fonte->path);
    }

    /**
     * Opens an AI-suggested post in the post generator: seeds the workshop brief
     * with the chosen angle AND the video's own transcript, then sends it to the
     * format selector. The angle alone is an instruction with no source material,
     * so the planner would invent the substance.
     */
    public function abrirPublicacao(
        string $fontePath,
        int $i,
        VaultContract $vault,
        ShortsPipeline $pipeline,
        FinishedContent $conteudo,
    ) {
        $fonte = $vault->get($fontePath);
        $sugestoes = $fonte ? json_decode((string) $fonte->get('publicacoes_sugeridas'), true) : null;
        $sugestao = is_array($sugestoes) ? ($sugestoes[$i] ?? null) : null;

        if (! $sugestao) {
            $this->notificar('Suggestion not found.', 'erro');

            return null;
        }

        $angulo = trim(($sugestao['titulo'] ?? '')."\n\n".($sugestao['angulo'] ?? ''));
        $transcricao = $conteudo->transcriptText($pipeline->transcricao($fonte));

        $brief = $transcricao !== ''
            ? $angulo."\n\n--- Source video transcript ---\n".$transcricao
            : $angulo;

        session(['oficina_brief' => Str::limit($brief, 20000, '')]);

        return redirect()->route('publicacoes');
    }

    /** Opens a long video (detail view). */
    public function abrirFonte(string $path): void
    {
        $this->fonteAberta = $path;
        $this->fechar();
    }

    /** Returns to the list of long videos. */
    public function voltarFontes(): void
    {
        $this->fonteAberta = null;
        $this->fechar();
    }

    public function transcrever(string $path, ShortsPipeline $pipeline): void
    {
        $this->executar(fn () => $pipeline->transcreverFonte($path), 'Transcription complete.');
    }

    public function removerFonte(string $path, VaultContract $vault): void
    {
        $vault->delete($path);

        if ($this->fonteAberta === $path) {
            $this->voltarFontes();
        }
    }

    // --- Clips --------------------------------------------------------

    public function adicionarClip(string $fontePath, string $slug, ShortsPipeline $pipeline): void
    {
        $inicio = trim($this->clipInicio[$slug] ?? '');
        $fim = trim($this->clipFim[$slug] ?? '');

        if ($inicio === '' || $fim === '') {
            $this->notificar('Enter the start and end of the clip.', 'erro');

            return;
        }

        $tags = collect(preg_split('/[,\n]/', $this->clipTags[$slug] ?? ''))
            ->map(fn ($t) => trim($t))->filter()->values()->all();

        $pipeline->criarClip($fontePath, trim($this->clipTitulo[$slug] ?? ''), $inicio, $fim, $tags);

        unset($this->clipTitulo[$slug], $this->clipInicio[$slug], $this->clipFim[$slug], $this->clipTags[$slug]);
        $this->notificar('Clip created.');
    }

    public function sugerirIA(string $fontePath, ShortsPipeline $pipeline, VaultContract $vault): void
    {
        try {
            $sugestoes = $pipeline->sugerirDoVideo($fontePath);
            $segmentos = $sugestoes['segments'];
            $publicacoes = $sugestoes['publications'];

            foreach ($segmentos as $s) {
                $pipeline->criarClip(
                    $fontePath,
                    (string) ($s['title'] ?? 'Clip'),
                    $s['start_time'] ?? 0,
                    $s['end_time'] ?? 0,
                    (array) ($s['tags'] ?? []),
                    descricao: (string) ($s['description'] ?? ''),
                );
            }

            // Saves the post ideas on the source (they persist and re-display).
            $vault->updateFrontmatter($fontePath, [
                'publicacoes_sugeridas' => json_encode($publicacoes, JSON_UNESCAPED_UNICODE),
            ]);

            $this->notificar(count($segmentos).' clips and '.count($publicacoes).' posts suggested by AI.');
        } catch (\Throwable $e) {
            $this->notificar($e->getMessage(), 'erro');
        }
    }

    /**
     * Send a cut clip into the animation pipeline as an overlay clip: the video
     * with animated layers drawn over it.
     *
     * It is the RAW cut that travels — bare picture and original audio, no burnt
     * captions and no music — because the animation pipeline writes its own
     * captions and mixes its own track. The file is COPIED onto the clips disk
     * (it lives under storage/app/shorts, outside that disk's root) so the Shorts
     * Generator keeps its own, and the short's own state is left untouched.
     *
     * Nothing is generated here: the file is staged and Animated Clips opens on its
     * usual form with that video already attached, so every option an uploaded
     * video gets is on the table. Music starts at none/0 there — nothing is being
     * carried over to clash with, and that form's picker owns the choice.
     */
    public function animar(string $path, VaultContract $vault, ShortsPipeline $pipeline): void
    {
        $this->executar(function () use ($path, $vault, $pipeline) {
            $clip = $vault->get($path);
            if (! $clip) {
                throw new \RuntimeException('Clip not found.');
            }

            // The RAW cut, never the finished short: the animation pipeline draws
            // its own captions, so handing it a video with captions already burnt
            // in puts two sets on screen in two different styles. Cutting adds no
            // captions and no music, which is exactly what the animator wants.
            $origem = (string) $clip->get('clip_path');
            if ($origem === '' || ! is_file($origem)) {
                $clip = $pipeline->cortarClip($path);
                $origem = (string) $clip->get('clip_path');
            }
            if ($origem === '' || ! is_file($origem)) {
                throw new \RuntimeException('Could not cut the clip to animate.');
            }

            $destino = Storage::disk(config('contentmachine.clips.disk'))->putFileAs(
                'clips/uploads',
                new File($origem),
                $clip->slug().'-'.Str::random(6).'.mp4',
            );

            // Hand the staged file to Animated Clips and let its own "Video with
            // animations" form take it from here — styles, lead, instructions,
            // backdrop and the rest are chosen there, exactly as for an upload.
            session([
                'animado_video' => $destino,
                'animado_video_nome' => (string) $clip->title(),
            ]);

            $this->redirect(route('clips-animados'), navigate: true);
        }, 'Clip ready to animate — choose the options and generate.');
    }

    public function gerarDescricao(string $path, ShortsPipeline $pipeline): void
    {
        try {
            $clip = $pipeline->gerarDescricao($path);

            if ($this->clipAberto === $path) {
                $this->clipDescricao = (string) $clip->get('descricao', '');
            }

            $this->notificar('Description generated by AI.');
        } catch (\Throwable $e) {
            $this->notificar($e->getMessage(), 'erro');
        }
    }

    public function cortar(string $path, ShortsPipeline $pipeline): void
    {
        // The framing only takes effect on a cut, so save the open panel first —
        // otherwise changing it and pressing Cut re-cuts the old way.
        if ($this->clipAberto === $path) {
            $this->persistirClipAberto($pipeline);
        }

        $this->executar(fn () => $pipeline->cortarClip($path), 'Clip cut.');
    }

    public function regenerar(string $path, ShortsPipeline $pipeline): void
    {
        if ($this->clipAberto === $path) {
            $this->persistirClipAberto($pipeline);
        }

        $this->executar(fn () => $pipeline->gravarLegendas($path), 'Short rendered with the captions.');
    }

    public function removerClip(string $path, VaultContract $vault): void
    {
        $vault->delete($path);

        if ($this->clipAberto === $path) {
            $this->fechar();
        }
    }

    // --- Caption editor ------------------------------------------

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
        $this->musica = (string) $clip->get('musica', '');
        $this->musicaVolume = (float) $clip->get('musica_volume', 0.1);
        // No recorded choice → what cortarClip would do anyway.
        $this->enquadramento = $pipeline->enquadramentoAtual($clip);
        $this->clipTituloEdit = (string) $clip->title();
        $this->clipDescricao = (string) $clip->get('descricao', '');
        $this->clipTagsEdit = implode(', ', (array) $clip->get('tags', []));
    }

    public function fechar(): void
    {
        $this->reset(
            'clipAberto', 'segmentos', 'estilo', 'modoPalavra', 'musica', 'musicaVolume',
            'clipTituloEdit', 'clipDescricao', 'clipTagsEdit',
        );
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
        if ($this->clipAberto === null) {
            return;
        }

        if ($this->persistirClipAberto($pipeline)) {
            // The frame only exists in the cut file, so a new one has to be cut and
            // re-rendered — saving the choice alone would leave the old size on disk.
            $this->executar(fn () => $pipeline->gravarLegendas($this->clipAberto), 'Changes saved — clip re-framed.');

            return;
        }

        $this->notificar('Changes saved.');
    }

    // --- Helpers ---------------------------------------------------

    /**
     * Persists everything the open clip's editor can change.
     *
     * @return bool whether the framing change threw away an existing cut.
     */
    private function persistirClipAberto(ShortsPipeline $pipeline): bool
    {
        if ($this->clipAberto === null) {
            return false;
        }

        $pipeline->guardarDetalhes($this->clipAberto, trim($this->clipTituloEdit), trim($this->clipDescricao), $this->tagsParaArray());
        $pipeline->guardarLegendas($this->clipAberto, $this->segmentosParaDados(), $this->estilo, $this->modoPalavra);
        $pipeline->definirMusica($this->clipAberto, trim($this->musica), $this->musicaVolume);

        return $pipeline->definirEnquadramento($this->clipAberto, $this->enquadramento);
    }

    /** @return array<int,string> */
    private function tagsParaArray(): array
    {
        return collect(preg_split('/[,\n]/', $this->clipTagsEdit))
            ->map(fn ($t) => trim($t))->filter()->values()->all();
    }

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
        try {
            $accao();
            $this->notificar($sucesso);
        } catch (\Throwable $e) {
            $this->notificar($e->getMessage(), 'erro');
        }
    }

    public function render(VaultContract $vault, MusicLibrary $lib)
    {
        $clips = $vault->all(ShortsPipeline::CLIPES, recursive: false);
        $clipsPorFonte = $clips->groupBy(fn ($c) => (string) $c->get('fonte_path'));
        $fontes = $vault->all(ShortsPipeline::FONTES);

        $fonteAtual = $this->fonteAberta ? $vault->get($this->fonteAberta) : null;
        if ($this->fonteAberta && ! $fonteAtual) {
            $this->fonteAberta = null; // the source was removed in the meantime
        }

        return view('livewire.clips', [
            'fontes' => $fontes,
            'clipsPorFonte' => $clipsPorFonte,
            'fonteAtual' => $fonteAtual,
            'clipsDaFonte' => $fonteAtual ? ($clipsPorFonte[$fonteAtual->path] ?? collect()) : collect(),
            'temIA' => app(ShortsPipeline::class)->temIA(),
            'linguaProjeto' => ProjectLanguage::code(),
            'fonteDesign' => (string) (app(DesignSystemRepository::class)->readTokens()['fonts']['body'] ?? ''),
            'musicas' => $lib->all(),
            'motor' => 'ffmpeg local · '.config('services.shorts.whisper_model').' (Whisper)',
            'modosPalavra' => ['karaoke', 'popup', 'typewriter', 'off'],
            'posicoes' => [
                'top-center', 'center-center', 'bottom-center',
                'top-left', 'center-left', 'bottom-left',
                'top-right', 'center-right', 'bottom-right',
            ],
        ]);
    }
}
