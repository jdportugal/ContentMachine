<?php

namespace Tests\Support;

use App\Services\Aggregation\YtDlpRunnerContract;

/**
 * Duplo de teste do runner do yt-dlp: devolve dados de fixtures em vez de
 * invocar o binário ou a rede. Permite testar os drivers de forma determinística.
 */
class FakeYtDlpRunner implements YtDlpRunnerContract
{
    /**
     * @param  array<int,array<string,mixed>>  $entradas  Entradas da listagem (flat-playlist), para qualquer canal.
     * @param  array<string,array<string,mixed>>  $metadados  Mapa url => metadados completos.
     * @param  string  $vtt  Conteúdo VTT devolvido por fetch().
     * @param  array<string,array<int,array<string,mixed>>>  $entradasPorNeedle  Mapa substring-do-canal => entradas
     *                                                                           (quando definido, só devolve entradas para canais que contenham a substring).
     */
    public function __construct(
        private array $entradas = [],
        private array $metadados = [],
        private string $vtt = '',
        private array $entradasPorNeedle = [],
    ) {}

    public function listing(string $channelUrl, int $limit): array
    {
        $entradas = $this->entradas;

        if ($this->entradasPorNeedle !== []) {
            $entradas = [];
            foreach ($this->entradasPorNeedle as $needle => $lista) {
                if (str_contains($channelUrl, (string) $needle)) {
                    $entradas = $lista;
                    break;
                }
            }
        }

        return ['_type' => 'playlist', 'entries' => array_slice($entradas, 0, $limit)];
    }

    public function metadata(string $url): array
    {
        return $this->metadados[$url] ?? [];
    }

    public function fetch(string $url): ?string
    {
        return $this->vtt !== '' ? $this->vtt : null;
    }
}
