<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;
use Illuminate\Support\Collection;

/**
 * Resolve o driver de monitorização por plataforma, segundo a config.
 */
class MonitoringManager
{
    public function __construct(private readonly EngagementScorer $scorer) {}

    public function plataformas(): array
    {
        return config('contentmachine.monitoring.plataformas', []);
    }

    public function driver(string $plataforma): MonitoringDriver
    {
        return match (config('contentmachine.monitoring.driver', 'fake')) {
            'api' => new ApiMonitoringDriver($plataforma, $this->scorer),
            default => new FakeMonitoringDriver($plataforma, $this->scorer),
        };
    }

    /** @return Collection<string, MonitoringDriver> */
    public function todos(): Collection
    {
        return collect($this->plataformas())
            ->mapWithKeys(fn (string $p) => [$p => $this->driver($p)]);
    }
}
