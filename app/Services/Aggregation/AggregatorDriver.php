<?php

namespace App\Services\Aggregation;

interface AggregatorDriver
{
    /** Nome da plataforma que este driver serve (ex.: 'youtube'). */
    public function plataforma(): string;

    /**
     * Recolhe os itens recentes de uma lista de canais/URLs.
     *
     * @param  array<int,string>  $canais
     * @return array<int,AggregatedItem>
     */
    public function collect(array $canais, int $limitePorCanal): array;
}
