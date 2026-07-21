<?php

namespace App\Services\Monitoring;

interface MonitoringDriver
{
    /** Identificador da plataforma: youtube|instagram|tiktok|linkedin. */
    public function plataforma(): string;

    /**
     * KPIs agregados do perfil.
     *
     * @return array<int, array{label:string, value:string, delta:float|null, unit:?string}>
     */
    public function resumo(): array;

    /**
     * Conteúdos recentes (mais recente primeiro), já com score de desempenho.
     *
     * @return array<int, array<string,mixed>>
     */
    public function conteudosRecentes(int $limite = 12): array;

    /**
     * Último conteúdo de cada tipo, com o respectivo desempenho.
     * (ênfase pedida: desempenho do último conteúdo de cada género)
     *
     * @return array<int, array<string,mixed>>
     */
    public function ultimoPorTipo(): array;

    /**
     * Melhores conteúdos por score.
     *
     * @return array<int, array<string,mixed>>
     */
    public function melhores(int $limite = 5): array;
}
