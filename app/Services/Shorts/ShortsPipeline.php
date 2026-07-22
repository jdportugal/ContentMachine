<?php

namespace App\Services\Shorts;

use App\Services\Aggregation\LlmClient;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;

/**
 * Orquestra o fluxo "vídeo longo → shorts" em PASSOS DISCRETOS e RE-EXECUTÁVEIS,
 * de forma LOCAL e independente (ffmpeg + Whisper via {@see LocalVideoEngine}),
 * persistindo cada peça como uma nota do vault (pasta `clips`).
 *
 * Fonte (tipo: clip-fonte):  o vídeo longo + a transcrição completa.
 * Clip  (tipo: clip):        janela [inicio,fim] + subtitle_data editável +
 *                            estilo + clip cortado + short final gravado.
 *
 * Passos:
 *   1. criarFonte()          — regista o vídeo de origem (caminho local ou URL).
 *   2. transcreverFonte()    — Whisper → transcrição completa (palavra a palavra).
 *   3. sugerirSegmentos()    — IA (OpenAI) escolhe janelas [opcional].
 *   4. criarClip()           — desloca a transcrição para a janela do clip.
 *   5. cortarClip()          — corta o clip do vídeo original (ffmpeg).
 *   6. gravarLegendas()      — grava as legendas ASS no clip cortado → short.
 *   7. adicionarMusica()     — mistura música de fundo [opcional].
 *
 * "Regenerar" = gravarLegendas() de novo com subtitle_data editado, SEM
 * voltar a cortar nem a transcrever.
 */
class ShortsPipeline
{
    public const FONTES = 'clips/fontes';

    public const CLIPES = 'clips';

    public function __construct(
        private readonly LocalVideoEngine $engine,
        private readonly VaultContract $vault,
        private readonly LlmClient $llm,
        private readonly MusicLibrary $musica,
    ) {}

    /** Há um fornecedor de IA disponível (CLI do Claude ou uma chave de API)? */
    public function temIA(): bool
    {
        return $this->llm->disponivel();
    }

    /** Estilo por defeito das legendas gravadas. */
    public static function estiloPorDefeito(): array
    {
        return [
            'position' => 'center-center',
            'font-size' => 90,
            'font-family' => 'Luckiest Guy',
            'outline-width' => 6,
            'line-color' => '#2dbab4',
            'highlight-color' => '#F5C542',
            'outline-color' => '#000000',
        ];
    }

    // --- 1. Fonte -----------------------------------------------------

    /** Regista um vídeo de origem (caminho local absoluto ou URL http). */
    public function criarFonte(string $ref, string $titulo = '', string $lingua = 'pt'): VaultNote
    {
        return $this->vault->create(self::FONTES, [
            'titulo' => $titulo !== '' ? $titulo : 'Fonte '.now()->format('d/m H:i'),
            'tipo' => 'clip-fonte',
            'fonte' => $ref,
            'lingua' => $lingua,
            'estado' => 'nova',
            'transcricao' => '',
        ], 'Vídeo de origem para geração de shorts.');
    }

    // --- 2. Transcrição ----------------------------------------------

    /**
     * Transcreve o vídeo completo (Whisper) e guarda a transcrição na fonte.
     * Passo re-executável; requer o script de transcrição com Whisper.
     */
    public function transcreverFonte(string $fontePath): VaultNote
    {
        $fonte = $this->exigirNota($fontePath);
        $origem = $this->engine->resolveSource((string) $fonte->get('fonte'), $this->tempDir());

        $transcricao = $this->engine->transcribe($origem, (string) $fonte->get('lingua', 'pt'));

        return $this->vault->updateFrontmatter($fonte->path, [
            'estado' => 'transcrita',
            'transcricao' => json_encode(array_values($transcricao), JSON_UNESCAPED_UNICODE),
        ]);
    }

    // --- 3. Sugestão de segmentos por IA -----------------------------

    /**
     * Escolhe automaticamente 3–10 segmentos (título, descrição, janela e tags)
     * a partir da transcrição, via IA — por defeito o CLI do Claude Code (sem
     * chave de API). É o equivalente ao "AI Agent" do fluxo n8n.
     *
     * @return array<int,array{title:string,description:string,start_time:mixed,end_time:mixed,tags:array}>
     */
    public function sugerirSegmentos(string $fontePath, int $quantidade = 5): array
    {
        if (! $this->llm->disponivel()) {
            throw new ShortsException('Sem IA disponível (o CLI do Claude não foi encontrado e não há chave de API).');
        }

        $fonte = $this->exigirNota($fontePath);
        $transcricao = $this->transcricao($fonte);

        if (empty($transcricao)) {
            throw new ShortsException('A fonte ainda não foi transcrita — transcreva primeiro.');
        }

        $texto = collect($transcricao)
            ->map(fn ($s) => sprintf('[%s-%s] %s', $s['start'] ?? 0, $s['end'] ?? 0, $s['text'] ?? ''))
            ->implode("\n");

        $prompt = "És um editor especialista em YouTube Shorts. A partir desta transcrição com marcas "
            ."temporais (em segundos), escolhe {$quantidade} segmentos autónomos e cativantes, cada um "
            .'com cerca de 60 segundos, que funcionem bem vistos isoladamente. Para cada um dá um título, '
            .'uma descrição, tags relevantes e as marcas de início/fim usando os tempos fornecidos. '
            ."Responde APENAS com JSON válido, sem texto à volta, no formato:\n"
            .'{"segments":[{"title":"","description":"","start_time":segundos,"end_time":segundos,"tags":["",""]}]}'
            ."\n\nTRANSCRIÇÃO:\n".$texto;

        $resposta = $this->llm->texto($prompt);

        if (blank($resposta)) {
            throw new ShortsException('A IA não devolveu sugestões.');
        }

        $dados = $this->extrairJson($resposta);

        return $dados['segments'] ?? [];
    }

    /**
     * Gera (via IA) uma descrição para um clip, a partir do seu texto de legendas
     * e título, e guarda-a. Útil para clips que ainda não têm descrição.
     */
    public function gerarDescricao(string $clipPath): VaultNote
    {
        if (! $this->llm->disponivel()) {
            throw new ShortsException('Sem IA disponível para gerar a descrição.');
        }

        $clip = $this->exigirNota($clipPath);

        $texto = collect($this->subtitleData($clip))
            ->map(fn ($s) => trim((string) ($s['text'] ?? '')))
            ->filter()
            ->implode(' ');

        if (blank($texto)) {
            $texto = $clip->title();
        }

        $tags = implode(', ', (array) $clip->get('tags', []));

        $prompt = "Escreve uma descrição curta e cativante (2 a 3 frases, em português de Portugal) para um YouTube Short, "
            ."pronta a publicar. Responde APENAS com a descrição — sem aspas, sem rótulos, sem hashtags.\n\n"
            ."Título: ".$clip->title()."\n"
            .($tags !== '' ? "Tags: {$tags}\n" : '')
            ."\nConteúdo falado no clip:\n".$texto;

        $descricao = $this->llm->texto($prompt);

        if (blank($descricao)) {
            throw new ShortsException('A IA não devolveu uma descrição.');
        }

        return $this->vault->updateFrontmatter($clip->path, [
            'descricao' => trim((string) $descricao),
        ]);
    }

    /** Extrai o objeto JSON da resposta do LLM (tolera cercas ```json e texto à volta). */
    private function extrairJson(string $resposta): array
    {
        $resposta = trim($resposta);

        // Remove cercas de código, se existirem.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $resposta, $m)) {
            $resposta = $m[1];
        } elseif (preg_match('/(\{.*\})/s', $resposta, $m)) {
            $resposta = $m[1];
        }

        $dados = json_decode($resposta, true);

        return is_array($dados) ? $dados : [];
    }

    // --- 4. Criar clip ------------------------------------------------

    /**
     * Cria uma nota de clip, deslocando a transcrição da fonte para a janela.
     * $inicio/$fim aceitam segundos ou "HH:MM:SS[.mmm]".
     */
    public function criarClip(
        string $fontePath,
        string $titulo,
        int|float|string $inicio,
        int|float|string $fim,
        array $tags = [],
        ?array $estilo = null,
        string $descricao = '',
    ): VaultNote {
        $fonte = $this->exigirNota($fontePath);

        $subtitleData = SubtitleShifter::shift($this->transcricao($fonte), $inicio, $fim);

        return $this->vault->create(self::CLIPES, [
            'titulo' => $titulo !== '' ? $titulo : 'Clip',
            'descricao' => $descricao,
            'tipo' => 'clip',
            'fonte_path' => $fonte->path,
            'fonte' => (string) $fonte->get('fonte'),
            'inicio' => (float) SubtitleShifter::parseTimeToSeconds($inicio),
            'fim' => (float) SubtitleShifter::parseTimeToSeconds($fim),
            'tags' => array_values($tags),
            'estado' => 'rascunho',
            'modo_palavra' => 'karaoke',
            'estilo' => $estilo ?? self::estiloPorDefeito(),
            'subtitle_data' => json_encode($subtitleData, JSON_UNESCAPED_UNICODE),
            'clip_path' => '',
            'output_path' => '',
            'musica' => '',
        ], 'Short gerado a partir de '.$fonte->title().'.');
    }

    /** Guarda os detalhes editáveis do clip (título, descrição, tags). */
    public function guardarDetalhes(string $clipPath, string $titulo, string $descricao, array $tags): VaultNote
    {
        return $this->vault->updateFrontmatter($clipPath, [
            'titulo' => $titulo !== '' ? $titulo : 'Clip',
            'descricao' => $descricao,
            'tags' => array_values($tags),
        ]);
    }

    /** Guarda subtitle_data (editado), estilo e modo de palavra num clip. */
    public function guardarLegendas(string $clipPath, array $subtitleData, array $estilo, string $modoPalavra = 'karaoke'): VaultNote
    {
        return $this->vault->updateFrontmatter($clipPath, [
            'subtitle_data' => json_encode(array_values($subtitleData), JSON_UNESCAPED_UNICODE),
            'estilo' => $estilo,
            'modo_palavra' => $modoPalavra,
        ]);
    }

    // --- 5. Cortar ----------------------------------------------------

    /** Corta o clip do vídeo original (ffmpeg). Guarda o caminho do clip cortado. */
    public function cortarClip(string $clipPath): VaultNote
    {
        $clip = $this->exigirNota($clipPath);
        $origem = $this->engine->resolveSource((string) $clip->get('fonte'), $this->tempDir());

        $destino = $this->clipDir($clip).'/raw.mp4';
        $this->engine->split($origem, (float) $clip->get('inicio'), (float) $clip->get('fim'), $destino);

        return $this->vault->updateFrontmatter($clip->path, [
            'clip_path' => $destino,
            'estado' => 'cortado',
        ]);
    }

    // --- 6. Gravar legendas / Regenerar ------------------------------

    /**
     * Grava as legendas ASS no clip já cortado, com o subtitle_data (editável)
     * e o estilo do clip. Se ainda não estiver cortado, corta primeiro. Se
     * houver música associada, mistura-a no fim.
     * É este o passo do botão "Regenerar" — não volta a transcrever.
     */
    public function gravarLegendas(string $clipPath): VaultNote
    {
        $clip = $this->exigirNota($clipPath);

        if (blank($clip->get('clip_path')) || ! is_file((string) $clip->get('clip_path'))) {
            $clip = $this->cortarClip($clipPath);
        }

        $subtitleData = $this->subtitleData($clip);
        $modo = (string) $clip->get('modo_palavra', 'karaoke');

        // Coerência texto ↔ palavras (karaoke) após edição do utilizador.
        foreach ($subtitleData as &$seg) {
            $seg['words'] = SubtitleShifter::alignWords(
                (string) ($seg['text'] ?? ''),
                (float) ($seg['start'] ?? 0),
                (float) ($seg['end'] ?? 0),
                $seg['words'] ?? [],
            );
        }
        unset($seg);

        $dir = $this->clipDir($clip);
        $legendado = $dir.'/final.mp4';

        $this->engine->burnSubtitles(
            (string) $clip->get('clip_path'),
            array_values($subtitleData),
            (array) $clip->get('estilo', self::estiloPorDefeito()),
            $modo,
            $legendado,
        );

        // Música de fundo: faixa específica ou, se não especificada, uma da
        // biblioteca escolhida ao acaso. 'nenhuma' desliga.
        $origemMusica = $this->resolverMusica((string) $clip->get('musica'));
        $final = $legendado;
        if ($origemMusica !== null) {
            $final = $dir.'/final-music.mp4';
            $this->engine->addMusic($legendado, $origemMusica, [
                'volume' => (float) $clip->get('musica_volume', 0.1),
                'fade_in' => 1.0,
                'fade_out' => 2.0,
                'loop_music' => true,
            ], $final);
        }

        return $this->vault->updateFrontmatter($clip->path, [
            'output_path' => $final,
            'output_bytes' => is_file($final) ? filesize($final) : 0,
            'estado' => 'pronto',
        ]);
    }

    /**
     * Associa a escolha de música de fundo ao clip.
     * $musica: '' (aleatória da biblioteca) | 'nenhuma' | nome de faixa da
     * biblioteca | caminho local/URL.
     */
    public function definirMusica(string $clipPath, string $musica, float $volume = 0.1): VaultNote
    {
        return $this->vault->updateFrontmatter($clipPath, [
            'musica' => $musica,
            'musica_volume' => $volume,
        ]);
    }

    /** A biblioteca de músicas (para a UI listar/carregar faixas). */
    public function biblioteca(): MusicLibrary
    {
        return $this->musica;
    }

    /**
     * Resolve a escolha de música para um caminho local, ou null (sem música).
     *   'nenhuma'         → sem música
     *   '' | 'aleatoria'  → faixa aleatória da biblioteca (null se vazia)
     *   caminho/URL       → esse ficheiro (descarregado se URL)
     *   nome de faixa     → faixa da biblioteca (aleatória se não existir)
     */
    private function resolverMusica(string $sel): ?string
    {
        $sel = trim($sel);

        if ($sel === 'nenhuma') {
            return null;
        }

        if ($sel === '' || $sel === 'aleatoria') {
            return $this->musica->randomPath();
        }

        if (preg_match('#^https?://#i', $sel) || str_contains($sel, '/')) {
            return $this->engine->resolveSource($sel, $this->tempDir());
        }

        return $this->musica->pathFor($sel) ?? $this->musica->randomPath();
    }

    // --- Auxiliares ---------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    public function subtitleData(VaultNote $clip): array
    {
        $raw = $clip->get('subtitle_data');
        $data = is_string($raw) ? json_decode($raw, true) : $raw;

        return is_array($data) ? $data : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function transcricao(VaultNote $fonte): array
    {
        $raw = $fonte->get('transcricao');
        $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : $raw;

        return is_array($data) ? $data : [];
    }

    /** Pasta de trabalho do clip (storage/app/shorts/{slug}). */
    private function clipDir(VaultNote $clip): string
    {
        $dir = storage_path('app/shorts/'.$clip->slug());
        @mkdir($dir, 0775, true);

        return $dir;
    }

    private function tempDir(): string
    {
        $dir = storage_path('app/shorts/_sources');
        @mkdir($dir, 0775, true);

        return $dir;
    }

    private function exigirNota(string $path): VaultNote
    {
        $nota = $this->vault->get($path);

        if (! $nota) {
            throw new ShortsException("Nota não encontrada: {$path}");
        }

        return $nota;
    }
}
