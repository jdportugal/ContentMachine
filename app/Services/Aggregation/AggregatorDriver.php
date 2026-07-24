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
     * @return array<int,AggregatedItem>
     */
    public function collect(array $canais, int $limitePorCanal): array;
}
