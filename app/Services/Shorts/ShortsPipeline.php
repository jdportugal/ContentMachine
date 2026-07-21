<?php

namespace App\Services\Shorts;

use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Facades\Http;

/**
 * Orquestra o fluxo "vídeo longo → shorts" em PASSOS DISCRETOS e RE-EXECUTÁVEIS,
 * persistindo cada peça como uma nota do vault (pasta `clips`).
 *
 * Fonte (tipo: clip-fonte):  o vídeo longo + a transcrição completa.
 * Clip  (tipo: clip):        janela [inicio,fim] + subtitle_data editável +
 *                            estilo + job de corte + output gravado.
 *
 * Passos:
 *   1. criarFonte()          — regista o vídeo de origem.
 *   2. transcreverFonte()    — /generate-subtitles (Whisper) → transcrição.
 *   3. sugerirSegmentos()    — IA (OpenAI) escolhe janelas [opcional].
 *   4. criarClip()           — desloca a transcrição para a janela do clip.
 *   5. cortarClip()          — /split-video (corta do original) → job de corte.
 *   6. gravarLegendas()      — /add-subtitles no clip já cortado → output.
 *
 * "Regenerar" = gravarLegendas() de novo com subtitle_data editado, SEM
 * voltar a cortar nem a transcrever.
 */
class ShortsPipeline
{
    public const FONTES = 'clips/fontes';

    public const CLIPES = 'clips';

    public function __construct(
        private readonly ShortsClient $client,
        private readonly VaultContract $vault,
    ) {}

    /** Estilo por defeito das legendas gravadas. */
    public static function estiloPorDefeito(): array
    {
        return [
            'position' => 'center-center',
            'font-size' => 90,
            'font-family' => 'Luckiest Guy',
            'outline-width' => 6,
            'line-color' => '#2dbab4',
            'outline-color' => '#000000',
        ];
    }

    // --- 1. Fonte -----------------------------------------------------

    /** Regista um vídeo de origem (URL acessível por HTTP). */
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
     * Passo re-executável; requer o serviço com Whisper disponível.
     */
    public function transcreverFonte(string $fontePath, int $timeout = 900): VaultNote
    {
        $fonte = $this->exigirNota($fontePath);
        $ref = (string) $fonte->get('fonte');

        $resposta = $this->client->generateSubtitles($ref, (string) $fonte->get('lingua', 'pt'));

        // Caso já exista transcrição no servidor para este project_id.
        if (($resposta['status'] ?? null) === 'already_exists') {
            $transcricao = $this->client->downloadSubtitlesData($resposta['project_id']);
        } else {
            if (empty($resposta['job_id'])) {
                throw new ShortsException('Transcrição sem job_id.');
            }
            $this->client->waitForJob($resposta['job_id'], $timeout);
            $transcricao = $this->client->downloadSubtitlesData($resposta['job_id']);
        }

        return $this->vault->updateFrontmatter($fonte->path, [
            'estado' => 'transcrita',
            'transcricao' => json_encode(array_values($transcricao), JSON_UNESCAPED_UNICODE),
        ]);
    }

    // --- 3. Sugestão de segmentos por IA -----------------------------

    /**
     * Usa a OpenAI (gpt-4.1) para escolher 3–10 janelas ~60s a partir da
     * transcrição. Requer OPENAI_API_KEY; caso contrário lança — a UI deve
     * degradar para entrada manual de janelas.
     *
     * @return array<int,array{title:string,description:string,start_time:string,end_time:string,tags:array}>
     */
    public function sugerirSegmentos(string $fontePath, int $quantidade = 5): array
    {
        $chave = config('services.openai.key');

        if (blank($chave)) {
            throw new ShortsException('Sem OPENAI_API_KEY — introduza as janelas dos clips manualmente.');
        }

        $fonte = $this->exigirNota($fontePath);
        $transcricao = $this->transcricao($fonte);

        if (empty($transcricao)) {
            throw new ShortsException('A fonte ainda não foi transcrita.');
        }

        $texto = collect($transcricao)
            ->map(fn ($s) => sprintf('[%s-%s] %s', $s['start'] ?? 0, $s['end'] ?? 0, $s['text'] ?? ''))
            ->implode("\n");

        $prompt = 'És um editor de shorts. A partir desta transcrição com marcas temporais (em segundos), '
            ."escolhe {$quantidade} segmentos autónomos e cativantes, cada um com ~60s. "
            .'Responde APENAS com JSON: {"segments":[{"title":"","description":"",'
            ."\"start_time\":segundos,\"end_time\":segundos,\"tags\":[\"\"]}]}.\n\nTRANSCRIÇÃO:\n".$texto;

        $resposta = Http::withToken($chave)
            ->timeout(120)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4.1',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'response_format' => ['type' => 'json_object'],
            ])
            ->throw()
            ->json();

        $conteudo = $resposta['choices'][0]['message']['content'] ?? '{}';
        $dados = json_decode($conteudo, true) ?: [];

        return $dados['segments'] ?? [];
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
    ): VaultNote {
        $fonte = $this->exigirNota($fontePath);

        $subtitleData = SubtitleShifter::shift($this->transcricao($fonte), $inicio, $fim);

        return $this->vault->create(self::CLIPES, [
            'titulo' => $titulo !== '' ? $titulo : 'Clip',
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
            'split_job_id' => '',
            'output_job_id' => '',
            'output_path' => '',
        ], 'Short gerado a partir de '.$fonte->title().'.');
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

    /** /split-video: corta o clip do vídeo original. Guarda o split_job_id. */
    public function cortarClip(string $clipPath, int $timeout = 600): VaultNote
    {
        $clip = $this->exigirNota($clipPath);

        $jobId = $this->client->splitVideo([
            'url' => (string) $clip->get('fonte'),
            'start_time' => SubtitleShifter::secondsToTimestamp((float) $clip->get('inicio')),
            'end_time' => SubtitleShifter::secondsToTimestamp((float) $clip->get('fim')),
        ]);

        $this->client->waitForJob($jobId, $timeout);

        return $this->vault->updateFrontmatter($clip->path, [
            'split_job_id' => $jobId,
            'estado' => 'cortado',
        ]);
    }

    // --- 6. Gravar legendas / Regenerar ------------------------------

    /**
     * /add-subtitles no clip já cortado, com o subtitle_data (editável) e o
     * estilo do clip. Se ainda não estiver cortado, corta primeiro.
     * É este o passo do botão "Regenerar" — não volta a transcrever.
     */
    public function gravarLegendas(string $clipPath, int $timeout = 600): VaultNote
    {
        $clip = $this->exigirNota($clipPath);

        if (blank($clip->get('split_job_id'))) {
            $clip = $this->cortarClip($clipPath, $timeout);
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

        $jobId = $this->client->addSubtitles([
            'job_id' => (string) $clip->get('split_job_id'),
            'subtitle_data' => array_values($subtitleData),
            'settings' => (array) $clip->get('estilo', self::estiloPorDefeito()),
            'word_level_mode' => $modo,
            'return_subtitles_file' => false,
        ]);

        $this->client->waitForJob($jobId, $timeout);

        $slug = pathinfo($clip->path, PATHINFO_FILENAME);
        $destino = storage_path('app/shorts/'.$slug.'.mp4');
        $bytes = $this->client->download($jobId, $destino);

        return $this->vault->updateFrontmatter($clip->path, [
            'output_job_id' => $jobId,
            'output_path' => $destino,
            'output_bytes' => $bytes,
            'estado' => 'pronto',
        ]);
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

    private function exigirNota(string $path): VaultNote
    {
        $nota = $this->vault->get($path);

        if (! $nota) {
            throw new ShortsException("Nota não encontrada: {$path}");
        }

        return $nota;
    }
}
