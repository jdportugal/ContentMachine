<?php

namespace App\Services\Monitoring;

use App\Services\Vault\VaultContract;
use Illuminate\Support\Carbon;

/**
 * A daily record of the channel totals, so the Dashboard can show what the
 * numbers were yesterday next to what they are now.
 *
 * MonitoringStore keeps ONE snapshot per platform and overwrites it on every
 * collection, so nothing in the app remembered a previous day. This writes the
 * summed totals to a dated note per day, in the vault rather than the cache:
 * the cache is `Cache::forever` but still a cache, and a history that a
 * `cache:clear` erases is not a history.
 *
 * One note per day, rewritten as the day's later collections come in, so a day
 * ends holding its final numbers.
 */
class MonitoringHistory
{
    private const FOLDER = 'monitorizacao/historico';

    private const TIPO = 'estatisticas-diarias';

    /** The metrics carried per day — the four the Dashboard cards show. */
    private const METRICAS = ['subscritores', 'publicacoes', 'visualizacoes', 'interacoes'];

    public function __construct(private readonly VaultContract $vault) {}

    /**
     * Record today's totals, replacing today's note if there already is one.
     *
     * @param  array<string,mixed>  $totais  as returned by MonitoringStats::totais()
     */
    public function registar(array $totais, ?Carbon $quando = null): void
    {
        $dia = ($quando ?? now())->toDateString();

        $metricas = [];
        foreach (self::METRICAS as $m) {
            $metricas[$m] = (int) ($totais[$m] ?? 0);
        }

        $this->vault->put(
            self::FOLDER.'/'.$dia.'.md',
            [
                'titulo' => 'Channel totals — '.$dia,
                'tipo' => self::TIPO,
                'data' => $dia,
                'metricas' => $metricas,
                'tags' => ['monitorizacao', 'estatisticas'],
            ],
            "Summed channel totals for {$dia}, written by the monitoring collection.\n"
        );
    }

    /**
     * The most recent recorded day BEFORE today, or null when there is none.
     *
     * Strictly before today, not "yesterday": a day the app was down leaves no
     * note, and the last figures we actually have beat showing nothing. The date
     * comes back with them so the view can say which day it is talking about.
     *
     * @return array{data:string,metricas:array<string,int>}|null
     */
    public function anterior(?Carbon $hoje = null): ?array
    {
        $limite = ($hoje ?? now())->toDateString();

        try {
            $nota = $this->vault->all(self::FOLDER)
                ->filter(fn ($n) => $n->get('tipo') === self::TIPO && is_string($n->get('data')))
                ->filter(fn ($n) => $n->get('data') < $limite)
                ->sortByDesc(fn ($n) => (string) $n->get('data'))
                ->first();
        } catch (\Throwable) {
            return null;   // the dashboard is never worth a 500
        }

        if ($nota === null) {
            return null;
        }

        $metricas = (array) $nota->get('metricas', []);
        $valores = [];
        foreach (self::METRICAS as $m) {
            $valores[$m] = (int) ($metricas[$m] ?? 0);
        }

        return ['data' => (string) $nota->get('data'), 'metricas' => $valores];
    }
}
