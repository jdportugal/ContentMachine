<?php

namespace App\Services\Aggregation;

/**
 * Fronteira fina em torno do binário yt-dlp e de pedidos HTTP simples.
 * Isolada para permitir substituição por um duplo de teste (fake) nos testes,
 * mantendo os drivers verificáveis sem rede.
 */
interface YtDlpRunnerContract
{
    /**
     * Lista os itens recentes de um canal/playlist (metadados leves, sem descarregar).
     *
     * @return array{_type?:string,entries?:array<int,array<string,mixed>>}
     */
    public function listing(string $channelUrl, int $limit): array;

    /**
     * Metadados completos de um único item (JSON do yt-dlp), sem descarregar média.
     *
     * @return array<string,mixed>
     */
    public function metadata(string $url): array;

    /** Descarrega o conteúdo textual de um URL (ex.: legendas VTT). Devolve null em falha. */
    public function fetch(string $url): ?string;
}
