<?php

namespace App\Services\Shorts;

use App\Services\Aggregation\LlmClient;
use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Projects\ProjectLanguage;
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

    /** Shortest clip the generator may produce — anything less doesn't hold a viewer. */
    public const MIN_SEGUNDOS = 30.0;

    /** A Short caps at a minute — the ceiling a boundary snap is not allowed to cross. */
    public const MAX_SEGUNDOS = 60.0;

    private readonly LlmClient $llm;

    public function __construct(
        private readonly LocalVideoEngine $engine,
        private readonly VaultContract $vault,
        LlmClient $llm,
        private readonly MusicLibrary $musica,
    ) {
        $this->llm = $llm->paraPasso('shorts_selecao');
    }

    /** Is there an AI provider available (Claude CLI or an API key)? */
    public function temIA(): bool
    {
        return $this->llm->disponivel();
    }

    /**
     * Default style of the burned-in subtitles: the SAME captions the animated
     * clips draw, so a short and an animated clip from one project read as one
     * brand. Every value tracks KaraokeTrack in remotion/src/scenes.tsx — a panel
     * in the alternate background colour, body text on it, the accent for the
     * word being spoken, four words to a line, low on the frame.
     *
     * Colours and face come from the project's Design System; the fallbacks are
     * the Nebula defaults baked into remotion/src/style-tokens.ts.
     */
    public static function estiloPorDefeito(): array
    {
        $tokens = app(DesignSystemRepository::class)->readTokens() ?? [];
        $cores = (array) ($tokens['colors'] ?? []);
        $fontes = (array) ($tokens['fonts'] ?? []);
        $painel = (string) ($cores['bgAlt'] ?? '#0C1225');

        return [
            'position' => 'bottom-center',
            // KaraokeTrack renders at 58px on a 1080x1920 frame, which is what a
            // portrait short is.
            'font-size' => 58,
            'font-family' => (string) ($fontes['body'] ?? 'Space Grotesk'),
            'line-color' => (string) ($cores['textOnBg'] ?? '#EAF0FF'),
            'highlight-color' => (string) ($cores['accent'] ?? '#FFB347'),
            'box-color' => $painel,
            'outline-color' => $painel,
            'outline-width' => 10, // padding around the text inside the panel
            'max-words-per-line' => 4,
        ];
    }

    // --- 1. Source ----------------------------------------------------

    /** Registers a source video (absolute local path or http URL). */
    public function criarFonte(string $ref, string $titulo = '', string $lingua = ''): VaultNote
    {
        return $this->vault->create(self::FONTES, [
            'titulo' => $titulo !== '' ? $titulo : 'Source '.now()->format('d/m H:i'),
            'tipo' => 'clip-fonte',
            'fonte' => $ref,
            'lingua' => $lingua !== '' ? $lingua : ProjectLanguage::code(),
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

        $lingua = (string) $fonte->get('lingua', '');
        $transcricao = $this->engine->transcribe($origem, $lingua !== '' ? $lingua : ProjectLanguage::code());

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

        $minimo = (int) self::MIN_SEGUNDOS;
        $maximo = (int) self::MAX_SEGUNDOS;
        $idioma = ProjectLanguage::name();

        $prompt = 'You are an expert YouTube Shorts creator. From this transcript, where every line is '
            ."prefixed with the [start-end] marks of what is said in it (in seconds), do TWO things:\n\n"
            ."LANGUAGE — read this first. Write EVERY word you produce in {$idioma}: segment titles, "
            .'descriptions and tags, and the post titles and angles. This is the PROJECT\'s language and it '
            .'OVERRIDES the transcript — the video below may be spoken in a different language, and you '
            ."still write your output in {$idioma}. Do NOT mirror the transcript's language.\n\n"
            ."1) Pick {$quantidade} segments for YouTube Shorts. For each one give a title, a description, "
            ."relevant tags and the start/end marks. Four rules, all of them hard:\n"
            .'   • STANDS ALONE — it must make full sense to someone who never saw the rest of the video. '
            .'No answer to a question the viewer never heard, no «as I said earlier», no pronoun whose '
            ."subject was named minutes ago. If it needs the context, it is the wrong segment.\n"
            .'   • NEVER CUT A SENTENCE — start_time MUST be copied from the START mark of a transcript '
            .'line and end_time from the END mark of a transcript line. Never a time in between, never a '
            .'rounded number of your own. Begin on the first word of a sentence and end on the last word '
            ."of one, so the clip opens and closes clean instead of mid-word.\n"
            ."   • LENGTH — every segment MUST last AT LEAST {$minimo} seconds "
            ."(end_time - start_time >= {$minimo}) and at most {$maximo}; aim for 45 to 60 seconds. A "
            .'shorter segment is invalid — widen it to the next sentence boundaries until it reaches the '
            ."minimum, or pick a different part of the video.\n"
            .'   • OPENS ON THE HOOK — the strongest line of the segment is its first line, not the '
            ."run-up to it. Cut the throat-clearing that leads into the point.\n"
            ."2) Propose {$publicacoes} social media post ideas inspired by the video. For each one "
            .'give a title and an angle (1 to 2 sentences to guide the writing of the post). '
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
            // The transcript's last mark is the timeline the model was given, so it
            // bounds the widening. Probing the file instead would make suggesting
            // fail whenever the source cannot be resolved right now.
            'segments' => self::aplicarDuracaoMinima(
                array_values((array) ($dados['segments'] ?? [])),
                (float) collect($transcricao)->max(fn ($seg) => (float) ($seg['end'] ?? 0)),
                $transcricao,
            ),
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

        $idioma = ProjectLanguage::name();

        $prompt = 'Write a short and captivating description (2 to 3 sentences) for a YouTube Short, '
            ."ready to publish. Write it in {$idioma} — the PROJECT's language — even when the clip is "
            .'spoken in another language; do NOT mirror the spoken language. '
            ."Respond ONLY with the description — no quotes, no labels, no hashtags.\n\n"
            .'Title: '.$clip->title()."\n"
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

    /**
     * Grow any segment shorter than MIN_SEGUNDOS around its midpoint until it
     * reaches the floor, clamped to the video. The prompt already demands the
     * floor — this is the guard for when the model ignores it, which is what
     * produced the too-short clips. A source shorter than the floor is left
     * alone: there is nothing to grow into.
     *
     * Given $transcricao it also snaps every window to the transcript's own
     * marks, before and after growing it. The prompt asks the model to cut on
     * sentence boundaries, but it is the growing that broke them: pushing the
     * edges out by half the shortfall lands mid-word by construction, so the
     * clip opened on «...and that is why» with no sentence in front of it.
     *
     * @param  array<int,array<string,mixed>>  $segmentos
     * @param  array<int,array<string,mixed>>  $transcricao  Segments carrying `start`/`end`, for the snap.
     * @return array<int,array<string,mixed>>
     */
    public static function aplicarDuracaoMinima(array $segmentos, float $duracaoFonte, array $transcricao = []): array
    {
        $limite = $duracaoFonte > 0 ? min($duracaoFonte, self::MIN_SEGUNDOS) : self::MIN_SEGUNDOS;
        $inicios = self::marcas($transcricao, 'start');
        $fins = self::marcas($transcricao, 'end');

        return array_map(function (array $s) use ($limite, $duracaoFonte, $inicios, $fins): array {
            $inicio = (float) ($s['start_time'] ?? 0);
            $fim = (float) ($s['end_time'] ?? 0);
            if ($fim <= $inicio) {
                return $s;
            }

            [$inicio, $fim] = self::alinhar($inicio, $fim, $inicios, $fins);

            if ($fim - $inicio >= $limite) {
                $s['start_time'] = round($inicio, 3);
                $s['end_time'] = round($fim, 3);

                return $s;
            }

            $falta = $limite - ($fim - $inicio);
            $inicio -= $falta / 2;
            $fim += $falta / 2;

            // Slid off an edge — push the whole window back inside instead of
            // truncating it, or the segment stays under the floor.
            if ($inicio < 0.0) {
                $fim -= $inicio;
                $inicio = 0.0;
            }
            if ($duracaoFonte > 0 && $fim > $duracaoFonte) {
                $inicio = max(0.0, $inicio - ($fim - $duracaoFonte));
                $fim = $duracaoFonte;
            }

            // Growing moved both edges off the marks again. Snap outward, so the
            // window can only stay at or above the floor it was just given.
            [$inicio, $fim] = self::alinhar($inicio, $fim, $inicios, $fins);

            $s['start_time'] = round($inicio, 3);
            $s['end_time'] = round($fim, 3);

            return $s;
        }, $segmentos);
    }

    /**
     * Pull a sorted, unique list of transcript marks out of the transcript.
     *
     * @param  array<int,array<string,mixed>>  $transcricao
     * @return array<int,float>
     */
    private static function marcas(array $transcricao, string $chave): array
    {
        $marcas = array_values(array_unique(array_map(
            fn ($seg) => (float) (is_array($seg) ? ($seg[$chave] ?? 0) : 0),
            $transcricao,
        )));
        sort($marcas);

        return $marcas;
    }

    /**
     * Move a window out onto the nearest transcript marks — start back to the
     * mark at or before it, end forward to the mark at or after it — so it opens
     * and closes on a whole sentence. Snapping only ever widens, so it is refused
     * when it would push the clip past MAX_SEGUNDOS: a transcript with no fine
     * marks nearby (one long block, say) would otherwise swallow the video, and a
     * window that is merely off the boundary beats one that is no longer a Short.
     *
     * @param  array<int,float>  $inicios
     * @param  array<int,float>  $fins
     * @return array{0:float,1:float}
     */
    private static function alinhar(float $inicio, float $fim, array $inicios, array $fins): array
    {
        if ($inicios === [] || $fins === []) {
            return [$inicio, $fim];
        }

        $antes = array_filter($inicios, fn (float $m) => $m <= $inicio + 0.001);
        $depois = array_filter($fins, fn (float $m) => $m >= $fim - 0.001);

        $novoInicio = $antes === [] ? $inicio : max($antes);
        $novoFim = $depois === [] ? $fim : min($depois);

        return $novoFim - $novoInicio > self::MAX_SEGUNDOS ? [$inicio, $fim] : [$novoInicio, $novoFim];
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
        $enquadramento = $this->enquadramentoDe($clip, $origem);
        $this->engine->split(
            $origem,
            (float) $clip->get('inicio'),
            (float) $clip->get('fim'),
            $destino,
            $enquadramento,
        );

        return $this->vault->updateFrontmatter($clip->path, [
            'clip_path' => $destino,
            // How this file is ACTUALLY framed. The `enquadramento` key is only the
            // author's intent; the two diverge whenever the choice is saved without
            // a re-cut, and only this one can tell us the cut is stale.
            'enquadramento_cortado' => $enquadramento,
            'estado' => 'cortado',
        ]);
    }

    /**
     * How this clip is framed when cut. The clip's own choice wins; clips recorded
     * before there was a choice carry the old `vertical` boolean, which maps onto
     * the two modes it used to mean. With neither, a landscape source fills the
     * 9:16 frame (a short is watched vertically) and a portrait one is left alone.
     */
    private function enquadramentoDe(VaultNote $clip, string $origem): string
    {
        $escolha = (string) $clip->get('enquadramento', '');
        if (in_array($escolha, LocalVideoEngine::FRAMING, true)) {
            return $escolha;
        }

        $antigo = $clip->get('vertical');
        if ($antigo !== null && $antigo !== '') {
            return filter_var($antigo, FILTER_VALIDATE_BOOL) ? 'fill' : 'original';
        }

        return $this->fonteEhHorizontal($clip, $origem) ? 'fill' : 'original';
    }

    /**
     * Record how this clip is framed. The frame is baked into the cut file, so a
     * change makes the cut on disk stale: drop it and the next render re-cuts with
     * the new framing (gravarLegendas already cuts when there is no clip_path).
     *
     * @return bool whether an existing cut was invalidated — the caller has to
     *              re-render for the new frame to reach the finished short.
     */
    public function definirEnquadramento(string $clipPath, string $enquadramento): bool
    {
        $clip = $this->exigirNota($clipPath);
        $novo = in_array($enquadramento, LocalVideoEngine::FRAMING, true) ? $enquadramento : 'original';

        // Compared against how the cut was actually made, never against the recorded
        // choice: a clip cut before the choice existed (or saved without re-cutting)
        // carries a frame nobody wrote down, and intent-vs-intent would call it up
        // to date and leave the old size on disk forever. No record → re-cut once.
        $recortar = filled($clip->get('clip_path'))
            && $novo !== (string) $clip->get('enquadramento_cortado', '');

        $dados = ['enquadramento' => $novo];
        if ($recortar) {
            $dados['clip_path'] = '';
        }
        $this->vault->updateFrontmatter($clipPath, $dados);

        return $recortar;
    }

    /** The framing the editor shows for a clip that has not chosen one. */
    public function enquadramentoAtual(VaultNote $clip): string
    {
        $escolha = (string) $clip->get('enquadramento', '');
        if (in_array($escolha, LocalVideoEngine::FRAMING, true)) {
            return $escolha;
        }

        $antigo = $clip->get('vertical');
        if ($antigo !== null && $antigo !== '') {
            return filter_var($antigo, FILTER_VALIDATE_BOOL) ? 'fill' : 'original';
        }

        return $this->fonteEhHorizontal($clip) ? 'fill' : 'original';
    }

    /** Is this clip's source a landscape video? (What the framing defaults from.) */
    public function fonteEhHorizontal(VaultNote $clip, ?string $origem = null): bool
    {
        try {
            $meta = $this->engine->probe($origem ?? $this->engine->resolveSource((string) $clip->get('fonte'), $this->tempDir()));
        } catch (\Throwable) {
            return false; // source unreachable — don't claim a shape we couldn't read
        }

        return $meta['width'] > 0 && $meta['height'] > 0 && $meta['width'] > $meta['height'];
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
