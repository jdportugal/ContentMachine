<?php

namespace App\Services\Monitoring;

interface MonitoringDriver
{
    /** Platform identifier: youtube|instagram|tiktok|linkedin. */
    public function plataforma(): string;

    /**
     * Aggregated profile KPIs.
     *
     * @return array<int, array{label:string, value:string, delta:float|null, unit:?string}>
     */
    public function resumo(): array;

    /**
     * Recent content (most recent first), already scored for performance.
     *
     * @return array<int, array<string,mixed>>
     */
    public function conteudosRecentes(int $limite = 12): array;

    /**
     * Latest content of each type, with its respective performance.
     * (requested emphasis: performance of the latest content of each kind)
     *
     * @return array<int, array<string,mixed>>
     */
    public function ultimoPorTipo(): array;

    /**
     * Best content by score.
     *
     * @return array<int, array<string,mixed>>
     */
    public function melhores(int $limite = 5): array;
}
