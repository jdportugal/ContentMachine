<?php

namespace App\Services\Aggregation;

use App\Services\Monitoring\ApifyClient;
use App\Services\Projects\ProjectLanguage;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Orchestrates multi-platform aggregation: collects items from the configured channels,
 * archives them in the vault organized BY DAY and generates, per day, a topics note
 * ("what has already been covered and is live").
 */
class NewsAggregator
{
    public function __construct(
        private readonly VaultContract $vault,
        private readonly SettingsRepository $definicoes,
        private readonly YtDlpRunnerContract $runner,
        private readonly TranscriptParser $parser,
        private readonly TopicsBuilder $topicos,
        private readonly LlmClient $llm,
        private readonly ApifyClient $apify,
    ) {}

    /**
     * Runs the aggregation and writes to the vault.
     *
     * @param  array<int,string>|null  $plataformas  Limits to these platforms (null = all configured).
     * @return array{gerado_em:string,total:int,por_plataforma:array<string,int>,dias:array<int,string>,avisos:array<int,string>}
     */
    public function aggregate(?array $plataformas = null, ?int $limite = null): array
    {
        $limite ??= (int) config('contentmachine.aggregation.limite_por_canal', 5);
        $canaisConfig = (array) $this->definicoes->get('canais', []);
        $plataformas ??= array_keys($canaisConfig);

        $todos = [];
        $porPlataforma = [];
        $avisos = [];
        $arquivados = $this->idsArquivadosPorPlataforma($this->vault->all('noticias'));

        foreach ($plataformas as $plataforma) {
            $canais = array_values(array_filter((array) ($canaisConfig[$plataforma] ?? [])));
            if ($canais === []) {
                continue;
            }

            $driver = $this->driver($plataforma);

            // Non-YouTube networks need Apify (actor + token). Skip cleanly with a
            // warning when it is not configured, instead of failing the run.
            if ($driver instanceof ApifyDriver && ! $driver->disponivel()) {
                $porPlataforma[$plataforma] = 0;
                $avisos[] = $this->avisoIndisponivel($plataforma);

                continue;
            }

            $jaArquivados = $arquivados[$plataforma] ?? [];
            $itens = $driver->collect($canais, $limite, $jaArquivados);

            // A concrete yt-dlp error (bot-check, extractor breakage, stale binary)
            // is a real failure — YouTube is then collected through Apify instead,
            // which reaches the same videos (subtitles included) another way.
            $erroYtDlp = $plataforma === 'youtube' ? $this->runner->lastError() : null;
            if ($erroYtDlp !== null) {
                [$itens, $aviso] = $this->recuperarComApify($canais, $limite, $jaArquivados, $itens, $erroYtDlp);
                $avisos[] = $aviso;
            }

            $porPlataforma[$plataforma] = count($itens);

            if ($itens === [] && $erroYtDlp === null && $jaArquivados === []) {
                $avisos[] = $this->avisoIndisponivel($plataforma);
            }

            foreach ($itens as $item) {
                $this->arquivarItem($item);
                $todos[] = $item;
            }
        }

        $dias = $this->arquivarTopicos($todos);

        return [
            'gerado_em' => now()->toIso8601String(),
            'total' => count($todos),
            'por_plataforma' => $porPlataforma,
            'dias' => $dias,
            'avisos' => $avisos,
        ];
    }

    /**
     * yt-dlp hit a wall on YouTube (typically "Sign in to confirm you're not a
     * bot"). Collect the same channels through Apify and keep whatever yt-dlp did
     * manage to get, merged by id. Returns the items plus the warning to show.
     *
     * @param  array<int,string>  $canais
     * @param  array<string,bool>  $jaArquivados
     * @param  array<int,AggregatedItem>  $itens  what yt-dlp collected before failing
     * @return array{0:array<int,AggregatedItem>,1:string}
     */
    private function recuperarComApify(array $canais, int $limite, array $jaArquivados, array $itens, string $erro): array
    {
        $apify = new ApifyDriver($this->apify, 'youtube', $this->parser);
        if (! $apify->disponivel()) {
            return [$itens, 'YouTube: '.$erro.' — configure an Apify token/actor to collect it anyway.'];
        }

        $porId = [];
        foreach ($itens as $item) {
            $porId[$item->id] = $item;
        }
        foreach ($apify->collect($canais, $limite, $jaArquivados) as $item) {
            $porId[$item->id] ??= $item;
        }

        $recuperados = array_values($porId);

        return [$recuperados, count($recuperados) > count($itens)
            ? 'YouTube: yt-dlp blocked ('.$erro.') — collected via Apify instead.'
            : 'YouTube: '.$erro.' — the Apify fallback returned nothing either.'];
    }

    /** Creates the driver suited to the platform: yt-dlp for YouTube, Apify for the rest. */
    private function driver(string $plataforma): AggregatorDriver
    {
        return $plataforma === 'youtube'
            ? new YtDlpDriver($this->runner, $this->parser, $plataforma)
            : new ApifyDriver($this->apify, $plataforma);
    }

    /**
     * Slugged item ids already archived, grouped by platform, so drivers can skip
     * re-fetching them. The id is the note filename minus the "{plataforma}-" prefix
     * (see AggregatedItem::caminho()).
     *
     * @param  Collection<int,VaultNote>  $notas
     * @return array<string,array<string,bool>>
     */
    private function idsArquivadosPorPlataforma(Collection $notas): array
    {
        $map = [];

        foreach ($notas as $nota) {
            if ($nota->get('tipo') !== 'item_agregado') {
                continue;
            }
            $plataforma = (string) $nota->get('plataforma');
            $id = Str::after(pathinfo($nota->path, PATHINFO_FILENAME), $plataforma.'-');
            if ($id !== '') {
                $map[$plataforma][$id] = true;
            }
        }

        return $map;
    }

    private function arquivarItem(AggregatedItem $item): void
    {
        $corpo = $item->descricao !== ''
            ? "## Descrição\n\n{$item->descricao}\n\n## Transcrição\n\n".($item->transcricao ?: '_Sem transcrição disponível._')
            : "## Transcrição\n\n".($item->transcricao ?: '_Sem transcrição disponível._');

        $this->vault->put($item->caminho(), [
            'titulo' => $item->titulo,
            'tipo' => 'item_agregado',
            'plataforma' => $item->plataforma,
            'canal' => $item->canal,
            'data' => $item->dia(),
            'url' => $item->url,
            'thumbnail' => $item->thumbnail,
            'descricao' => Str::of($item->descricao)->squish()->limit(800)->toString(),
            'resumo' => $this->resumoDoItem($item),
            'tags' => $item->tags,
            'fontes' => $item->fontes,
        ], $corpo);
    }

    /**
     * Content summary (1-2 sentences) of what the video COVERS, generated by LLM
     * from the transcript — not the author's promotional description. Without LLM or
     * without a transcript returns empty (the interface falls back to the start of the transcript).
     */
    private function resumoDoItem(AggregatedItem $item): string
    {
        $transcricao = trim($item->transcricao);
        if ($transcricao === '' || ! config('contentmachine.aggregation.gerar_resumos', true) || ! $this->llm->disponivel()) {
            return '';
        }

        $prompt = 'In 1 to 2 short sentences and in '.ProjectLanguage::name().', summarize WHAT THIS VIDEO COVERS '
            .'(the content addressed, not promotions, sponsorships or links). Respond only with the summary, no quotes.'
            ."\n\nTitle: {$item->titulo}\nTranscript (excerpt): ".Str::limit($transcricao, 3000, '');

        $resumo = $this->llm->texto($prompt);

        return $resumo !== null ? Str::of($resumo)->squish()->limit(400)->toString() : '';
    }

    /**
     * Groups by day and writes/updates each day's topics note.
     *
     * @param  array<int,AggregatedItem>  $itens
     * @return array<int,string> affected days (YYYY-MM-DD)
     */
    private function arquivarTopicos(array $itens): array
    {
        $porDia = [];
        foreach ($itens as $item) {
            $porDia[$item->dia()][] = $item;
        }

        krsort($porDia);

        foreach ($porDia as $dia => $itensDoDia) {
            $resultado = $this->topicos->build($itensDoDia);
            $this->vault->put(
                "noticias/{$dia}/topicos.md",
                [
                    'titulo' => "Tópicos — {$dia}",
                    'tipo' => 'topicos',
                    'data' => $dia,
                    'metodo' => $resultado['metodo'],
                    'total' => count($itensDoDia),
                ],
                $this->corpoTopicos($dia, $resultado, count($itensDoDia)),
            );
        }

        return array_keys($porDia);
    }

    /** @param array<string,mixed> $resultado */
    private function corpoTopicos(string $dia, array $resultado, int $total): string
    {
        $linhas = [
            "# Tópicos cobertos — {$dia}",
            '',
            "> {$total} item(s) agregado(s) · método: {$resultado['metodo']} · {$resultado['gerado_em']}",
            '',
        ];

        foreach ($resultado['topicos'] as $topico) {
            $linhas[] = "## {$topico['topico']}";
            $linhas[] = '';

            foreach ($topico['itens'] as $it) {
                $plataforma = $it['plataforma'] ?? '';
                $url = $it['url'] ?? '';
                $titulo = $it['titulo'] ?? '';
                $linhas[] = $url !== ''
                    ? "- **[{$plataforma}]** [{$titulo}]({$url})"
                    : "- **[{$plataforma}]** {$titulo}";
            }

            if (! empty($topico['fontes'])) {
                $linhas[] = '';
                $linhas[] = '  Fontes: '.implode(' · ', array_map(fn ($f) => "<{$f}>", $topico['fontes']));
            }

            $linhas[] = '';
        }

        return implode("\n", $linhas);
    }

    private function avisoIndisponivel(string $plataforma): string
    {
        return match ($plataforma) {
            'instagram', 'tiktok', 'linkedin' => ucfirst($plataforma).': no items collected — check the Apify actor/token in Settings and the profile URLs.',
            default => ucfirst($plataforma).': no items collected (channel unreachable or no recent posts).',
        };
    }
}
