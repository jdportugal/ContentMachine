<?php

namespace App\Services\Aggregation;

interface AggregatorDriver
{
    /** Name of the platform this driver serves (e.g. 'youtube'). */
    public function plataforma(): string;

    /**
     * Collects the recent items from a list of channels/URLs.
     *
     * @param  array<int,string>  $canais
     * @param  array<string,bool>  $idsArquivados  slugged item ids already in the vault, to skip re-fetching
     * @return array<int,AggregatedItem>
     */
    public function collect(array $canais, int $limitePorCanal, array $idsArquivados = []): array;
}
