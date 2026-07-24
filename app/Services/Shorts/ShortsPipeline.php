<?php

namespace App\Services\Shorts;

use App\Services\Aggregation\LlmClient;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;

/**
 * Orchestrates the "long video → shorts" flow in DISCRETE and RE-RUNNABLE STEPS,
 * LOCALLY and independently (ffmpeg + Whisper via {@see LocalVideoEngine}),
 * persisting each piece as a vault note (`clips` folder).
 *
 * Source (type: clip-fonte):  the long video + the full transcript.
 * Clip   (type: clip):        window [inicio,fim] + editable subtitle_data +
 *                             style + cut clip + final rendered short.
 *
 * Steps:
 *   1. criarFonte()          — registers the source video (local path or URL).
 *   2. transcreverFonte()    — Whisper → full transcript (word by word).
 *   3. sugerirDoVideo()      — AI picks short windows + post ideas [optional].
 *   4. criarClip()           — shifts the transcript to the clip window.
 *   5. cortarClip()          — cuts the clip from the original video (ffmpeg).
 *   6. gravarLegendas()      — burns the ASS subtitles into the cut clip → short.
 *   7. adicionarMusica()     — mixes in background music [optional].
 *
 * "Regenerate" = gravarLegendas() again with edited subtitle_data, WITHOUT
 * cutting or transcribing again.
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

    /** Is there an AI provider available (Claude CLI or an API key)? */
    public function temIA(): bool
    {
        return $this->llm->disponivel();
    }

    /** Default style of the burned-in subtitles. */
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

    // --- 1. Source ----------------------------------------------------

    /** Registers a source video (absolute local path or http URL). */
    public function criarFonte(string $ref, string $titulo = '', string $lingua = 'pt'): VaultNote
    {
        return $this->vault->create(self::FONTES, [
            'titulo' => $titulo !== '' ? $titulo : 'Source '.now()->format('d/m H:i'),
            'tipo' => 'clip-fonte',
            'fonte' => $ref,
            'lingua' => $lingua,
            'estado' => 'nova',
            'transcricao' => '',
        ], 'Source video for shorts generation.');
    }

    // --- 2. Transcript -----------------------------------------------

    /**
     * Transcribes the full video (Whisper) and stores the transcript on the source.
     * Re-runnable step; requires the Whisper transcription script.
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

    // --- 3. AI segment suggestion ------------------------------------

    /**
     * Automatically picks 3–10 segments (title, description, window and tags)
     * from the transcript, via AI — by default the Claude Code CLI (no
     * API key). It is the equivalent of the "AI Agent" in the n8n flow.
     *
     * @return array<int,array{title:string,description:string,start_time:mixed,end_time:mixed,tags:array}>
     */
    /**
     * In a single AI request, from the video transcript:
     *   • picks segments for shorts (title, description, window, tags);
     *   • proposes post ideas (title + angle) for the post generator.
     *
     * @return array{segments:array<int,array<string,mixed>>,publications:array<int,array{titulo:string,angulo:string}>}
     */
    public function sugerirDoVideo(string $fontePath, int $quantidade = 5, int $publicacoes = 3): array
    {
        if (! $this->llm->disponivel()) {
            throw new ShortsException('No AI available (the Claude CLI was not found and there is no API key).');
        }

        $fonte = $this->exigirNota($fontePath);
        $transcricao = $this->transcricao($fonte);

        if (empty($transcricao)) {
            throw new ShortsException('The source has not been transcribed yet — transcribe it first.');
        }

        $texto = collect($transcricao)
            ->map(fn ($s) => sprintf('[%s-%s] %s', $s['start'] ?? 0, $s['end'] ?? 0, $s['text'] ?? ''))
            ->implode("\n");

        $prompt = "You are an expert editor of social media content. From this transcript with "
            ."timestamps (in seconds), do TWO things:\n"
            ."1) Pick {$quantidade} self-contained and captivating segments for YouTube Shorts, each about "
            .'60 seconds long, that work well watched in isolation. For each one give a title, a description, '
            ."relevant tags and the start/end marks using the given times.\n"
            ."2) Propose {$publicacoes} social media post ideas inspired by the video. For each one "
            .'give a title and an angle (1 to 2 sentences to guide the writing of the post, in European Portuguese). '
            ."Respond ONLY with valid JSON, with no surrounding text, in the format:\n"
            .'{"segments":[{"title":"","description":"","start_time":seconds,"end_time":seconds,"tags":["",""]}],'
            .'"publications":[{"titulo":"","angulo":""}]}'
            ."\n\nTRANSCRIPT:\n".$texto;

        $resposta = $this->llm->texto($prompt);

        if (blank($resposta)) {
            throw new ShortsException('The AI returned no suggestions.');
        }

        $dados = $this->extrairJson($resposta);

        return [
            'segments' => $dados['segments'] ?? [],
            'publications' => collect($dados['publications'] ?? [])
                ->map(fn ($p) => [
                    'titulo' => trim((string) ($p['titulo'] ?? $p['title'] ?? '')),
                    'angulo' => trim((string) ($p['angulo'] ?? $p['angle'] ?? '')),
                ])
                ->filter(fn ($p) => $p['titulo'] !== '' || $p['angulo'] !== '')
                ->values()
                ->all(),
        ];
    }

    /**
     * Generates (via AI) a description for a clip, from its subtitle text
     * and title, and stores it. Useful for clips that do not have a description yet.
     */
    public function gerarDescricao(string $clipPath): VaultNote
    {
        if (! $this->llm->disponivel()) {
            throw new ShortsException('No AI available to generate the description.');
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

        $prompt = "Write a short and captivating description (2 to 3 sentences, in European Portuguese) for a YouTube Short, "
            ."ready to publish. Respond ONLY with the description — no quotes, no labels, no hashtags.\n\n"
            ."Title: ".$clip->title()."\n"
            .($tags !== '' ? "Tags: {$tags}\n" : '')
            ."\nSpoken content in the clip:\n".$texto;

        $descricao = $this->llm->texto($prompt);

        if (blank($descricao)) {
            throw new ShortsException('The AI returned no description.');
        }

        return $this->vault->updateFrontmatter($clip->path, [
            'descricao' => trim((string) $descricao),
        ]);
    }

    /** Extracts the JSON object from the LLM response (tolerates ```json fences and surrounding text). */
    private function extrairJson(string $resposta): array
    {
        $resposta = trim($resposta);

        // Remove code fences, if present.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $resposta, $m)) {
            $resposta = $m[1];
        } elseif (preg_match('/(\{.*\})/s', $resposta, $m)) {
            $resposta = $m[1];
        }

        $dados = json_decode($resposta, true);

        return is_array($dados) ? $dados : [];
    }

    // --- 4. Create clip -----------------------------------------------

    /**
     * Creates a clip note, shifting the source transcript into the window.
     * $inicio/$fim accept seconds or "HH:MM:SS[.mmm]".
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
        ], 'Short generated from '.$fonte->title().'.');
    }

    /** Stores the editable clip details (title, description, tags). */
    public function guardarDetalhes(string $clipPath, string $titulo, string $descricao, array $tags): VaultNote
    {
        return $this->vault->updateFrontmatter($clipPath, [
            'titulo' => $titulo !== '' ? $titulo : 'Clip',
            'descricao' => $descricao,
            'tags' => array_values($tags),
        ]);
    }

    /** Stores subtitle_data (edited), style and word mode on a clip. */
    public function guardarLegendas(string $clipPath, array $subtitleData, array $estilo, string $modoPalavra = 'karaoke'): VaultNote
    {
        return $this->vault->updateFrontmatter($clipPath, [
            'subtitle_data' => json_encode(array_values($subtitleData), JSON_UNESCAPED_UNICODE),
            'estilo' => $estilo,
            'modo_palavra' => $modoPalavra,
        ]);
    }

    // --- 5. Cut -------------------------------------------------------

    /** Cuts the clip from the original video (ffmpeg). Stores the path of the cut clip. */
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

    // --- 6. Burn subtitles / Regenerate ------------------------------

    /**
     * Burns the ASS subtitles into the already-cut clip, with the subtitle_data (editable)
     * and the clip style. If it is not cut yet, cuts first. If
     * there is associated music, mixes it in at the end.
     * This is the "Regenerate" button step — it does not transcribe again.
     */
    public function gravarLegendas(string $clipPath): VaultNote
    {
        $clip = $this->exigirNota($clipPath);

        if (blank($clip->get('clip_path')) || ! is_file((string) $clip->get('clip_path'))) {
            $clip = $this->cortarClip($clipPath);
        }

        $subtitleData = $this->subtitleData($clip);
        $modo = (string) $clip->get('modo_palavra', 'karaoke');

        // Text ↔ words (karaoke) consistency after user editing.
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

        // Background music: a specific track or, if not specified, a random one
        // from the library. 'nenhuma' turns it off.
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
     * Associates the background music choice with the clip.
     * $musica: '' (random from library) | 'nenhuma' | library track
     * name | local path/URL.
     */
    public function definirMusica(string $clipPath, string $musica, float $volume = 0.1): VaultNote
    {
        return $this->vault->updateFrontmatter($clipPath, [
            'musica' => $musica,
            'musica_volume' => $volume,
        ]);
    }

    /** The music library (for the UI to list/load tracks). */
    public function biblioteca(): MusicLibrary
    {
        return $this->musica;
    }

    /**
     * Resolves the music choice to a local path, or null (no music).
     *   'nenhuma'         → no music
     *   '' | 'aleatoria'  → random track from the library (null if empty)
     *   path/URL          → that file (downloaded if URL)
     *   track name        → library track (random if it does not exist)
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

    // --- Helpers ------------------------------------------------------

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

    /** Working folder of the clip (storage/app/shorts/{slug}). */
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
            throw new ShortsException("Note not found: {$path}");
        }

        return $nota;
    }
}
