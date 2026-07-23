<?php

namespace App\Livewire;

use App\Services\Shorts\MusicLibrary;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Gerador de Clips')]
class Clips extends Component
{
    use WithFileUploads;
    // Vídeo longo aberto (detalhe). Null → lista de vídeos.
    public ?string $fonteAberta = null;

    // Mostrar o formulário de novo vídeo (revelado por botão).
    public bool $mostrarNovaFonte = false;

    // Formulário "nova fonte".
    public string $novaFonte = '';

    public string $novaFonteTitulo = '';

    public string $novaFonteLingua = 'pt';

    // Vídeo longo carregado por arrastar-e-largar (até 2 GB — ver config/livewire.php).
    public $novoVideo = null;

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

    // Detalhes editáveis do clip aberto (título, descrição, tags).
    public string $clipTituloEdit = '';

    public string $clipDescricao = '';

    public string $clipTagsEdit = '';

    // Escolha de música do clip aberto: '' (aleatória) | 'nenhuma' | nome de faixa.
    public string $musica = '';

    public float $musicaVolume = 0.1;

    // --- Fontes (vídeos longos) ---------------------------------------

    /** Envia uma notificação (toast) para o cliente. */
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
        // Aceita um ficheiro carregado OU um caminho/URL — pelo menos um.
        $this->validate([
            'novoVideo' => 'nullable|file|mimetypes:video/mp4,video/quicktime|max:2097152',
            'novaFonte' => 'nullable|string',
        ], [
            'novoVideo.mimetypes' => 'Formato de vídeo não suportado. Use mp4 ou mov.',
            'novoVideo.max' => 'O vídeo é demasiado grande (máximo 2 GB).',
        ]);

        if ($this->novoVideo) {
            // Caminho absoluto: LocalVideoEngine::resolveSource faz is_file($ref).
            $ref = Storage::disk('local')->path($this->novoVideo->store('clips/uploads'));
            $titulo = trim($this->novaFonteTitulo) !== ''
                ? trim($this->novaFonteTitulo)
                : $this->novoVideo->getClientOriginalName();
        } elseif (trim($this->novaFonte) !== '') {
            $ref = trim($this->novaFonte);
            $titulo = trim($this->novaFonteTitulo);
        } else {
            $this->addError('novaFonte', 'Arraste um vídeo ou indique um caminho/URL.');

            return;
        }

        $fonte = $pipeline->criarFonte($ref, $titulo, $this->novaFonteLingua);

        $this->reset('novaFonte', 'novaFonteTitulo', 'novoVideo');
        $this->mostrarNovaFonte = false;
        $this->notificar('Vídeo adicionado.');

        // Abre logo o vídeo para prosseguir (transcrever → escolher clips).
        $this->abrirFonte($fonte->path);
    }

    /**
     * Abre uma publicação sugerida pela IA no gerador de posts: semeia o brief
     * da oficina com o título+ângulo e envia para o seletor de formato.
     */
    public function abrirPublicacao(string $fontePath, int $i, VaultContract $vault)
    {
        $fonte = $vault->get($fontePath);
        $sugestoes = $fonte ? json_decode((string) $fonte->get('publicacoes_sugeridas'), true) : null;
        $sugestao = is_array($sugestoes) ? ($sugestoes[$i] ?? null) : null;

        if (! $sugestao) {
            $this->notificar('Sugestão não encontrada.', 'erro');

            return null;
        }

        $brief = trim(($sugestao['titulo'] ?? '')."\n\n".($sugestao['angulo'] ?? ''));
        session(['oficina_brief' => Str::limit($brief, 6000, '')]);

        return redirect()->route('publicacoes');
    }

    /** Abre um vídeo longo (vista de detalhe). */
    public function abrirFonte(string $path): void
    {
        $this->fonteAberta = $path;
        $this->fechar();
    }

    /** Volta à lista de vídeos longos. */
    public function voltarFontes(): void
    {
        $this->fonteAberta = null;
        $this->fechar();
    }

    public function transcrever(string $path, ShortsPipeline $pipeline): void
    {
        $this->executar(fn () => $pipeline->transcreverFonte($path), 'Transcrição concluída.');
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
            $this->notificar('Indique o início e o fim do clip.', 'erro');

            return;
        }

        $tags = collect(preg_split('/[,\n]/', $this->clipTags[$slug] ?? ''))
            ->map(fn ($t) => trim($t))->filter()->values()->all();

        $pipeline->criarClip($fontePath, trim($this->clipTitulo[$slug] ?? ''), $inicio, $fim, $tags);

        unset($this->clipTitulo[$slug], $this->clipInicio[$slug], $this->clipFim[$slug], $this->clipTags[$slug]);
        $this->notificar('Clip criado.');
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

            // Guarda as ideias de publicação na fonte (persistem e re-exibem).
            $vault->updateFrontmatter($fontePath, [
                'publicacoes_sugeridas' => json_encode($publicacoes, JSON_UNESCAPED_UNICODE),
            ]);

            $this->notificar(count($segmentos).' clips e '.count($publicacoes).' publicações sugeridas pela IA.');
        } catch (\Throwable $e) {
            $this->notificar($e->getMessage(), 'erro');
        }
    }

    public function gerarDescricao(string $path, ShortsPipeline $pipeline): void
    {
        try {
            $clip = $pipeline->gerarDescricao($path);

            if ($this->clipAberto === $path) {
                $this->clipDescricao = (string) $clip->get('descricao', '');
            }

            $this->notificar('Descrição gerada pela IA.');
        } catch (\Throwable $e) {
            $this->notificar($e->getMessage(), 'erro');
        }
    }

    public function cortar(string $path, ShortsPipeline $pipeline): void
    {
        $this->executar(fn () => $pipeline->cortarClip($path), 'Clip cortado.');
    }

    public function regenerar(string $path, ShortsPipeline $pipeline): void
    {
        if ($this->clipAberto === $path) {
            $this->persistirClipAberto($pipeline);
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
        $this->musica = (string) $clip->get('musica', '');
        $this->musicaVolume = (float) $clip->get('musica_volume', 0.1);
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

        $this->persistirClipAberto($pipeline);
        $this->notificar('Alterações guardadas.');
    }

    // --- Auxiliares ---------------------------------------------------

    /** Persiste tudo o que o editor do clip aberto pode alterar. */
    private function persistirClipAberto(ShortsPipeline $pipeline): void
    {
        if ($this->clipAberto === null) {
            return;
        }

        $pipeline->guardarDetalhes($this->clipAberto, trim($this->clipTituloEdit), trim($this->clipDescricao), $this->tagsParaArray());
        $pipeline->guardarLegendas($this->clipAberto, $this->segmentosParaDados(), $this->estilo, $this->modoPalavra);
        $pipeline->definirMusica($this->clipAberto, trim($this->musica), $this->musicaVolume);
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
            $this->fonteAberta = null; // a fonte foi removida entretanto
        }

        return view('livewire.clips', [
            'fontes' => $fontes,
            'clipsPorFonte' => $clipsPorFonte,
            'fonteAtual' => $fonteAtual,
            'clipsDaFonte' => $fonteAtual ? ($clipsPorFonte[$fonteAtual->path] ?? collect()) : collect(),
            'temIA' => app(ShortsPipeline::class)->temIA(),
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
