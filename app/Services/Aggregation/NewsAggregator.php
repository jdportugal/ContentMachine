<?php

namespace App\Services\Aggregation;

use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use Illuminate\Support\Str;

/**
 * Orquestra a agregação multi-plataforma: recolhe itens dos canais configurados,
 * arquiva-os no vault organizados POR DIA e gera, por dia, uma nota de tópicos
 * ("o que já foi coberto e está no ar").
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
    ) {}

    /**
     * Corre a agregação e escreve no vault.
     *
     * @param  array<int,string>|null  $plataformas  Limita a estas plataformas (null = todas configuradas).
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

        foreach ($plataformas as $plataforma) {
            $canais = array_values(array_filter((array) ($canaisConfig[$plataforma] ?? [])));
            if ($canais === []) {
                continue;
            }

            $itens = $this->driver($plataforma)->collect($canais, $limite);
            $porPlataforma[$plataforma] = count($itens);

            if ($itens === []) {
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

    /** Cria o driver adequado à plataforma. Todas usam yt-dlp neste momento. */
    private function driver(string $plataforma): AggregatorDriver
    {
        return new YtDlpDriver($this->runner, $this->parser, $plataforma);
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
     * Resumo de conteúdo (1-2 frases) do que o vídeo ABORDA, gerado por LLM a
     * partir da transcrição — não a descrição promocional do autor. Sem LLM ou
     * sem transcrição devolve vazio (a interface cai para o início da transcrição).
     */
    private function resumoDoItem(AggregatedItem $item): string
    {
        $transcricao = trim($item->transcricao);
        if ($transcricao === '' || ! config('contentmachine.aggregation.gerar_resumos', true) || ! $this->llm->disponivel()) {
            return '';
        }

        $prompt = 'Em 1 a 2 frases curtas e em PORTUGUÊS EUROPEU, resume O QUE ESTE VÍDEO ABORDA '
            .'(o conteúdo tratado, não promoções, patrocínios nem links). Responde apenas com o resumo, sem aspas.'
            ."\n\nTítulo: {$item->titulo}\nTranscrição (excerto): ".Str::limit($transcricao, 3000, '');

        $resumo = $this->llm->texto($prompt);

        return $resumo !== null ? Str::of($resumo)->squish()->limit(400)->toString() : '';
    }

    /**
     * Agrupa por dia e escreve/actualiza a nota de tópicos de cada dia.
     *
     * @param  array<int,AggregatedItem>  $itens
     * @return array<int,string> dias afectados (YYYY-MM-DD)
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
            'instagram', 'linkedin' => ucfirst($plataforma).': não disponível sem credenciais (extração via yt-dlp requer autenticação).',
            default => ucfirst($plataforma).': nenhum item recolhido (canal inacessível ou sem publicações recentes).',
        };
    }
}
