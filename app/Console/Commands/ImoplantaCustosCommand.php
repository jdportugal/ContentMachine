<?php

namespace App\Console\Commands;

use App\Services\Imoplanta\ImoplantaCustos;
use Illuminate\Console\Command;

/** The admin page's cost-vs-revenue summary for Imoplanta, in the terminal. */
class ImoplantaCustosCommand extends Command
{
    protected $signature = 'imoplanta:custos {--imoveis : List every property too}';

    protected $description = 'Imoplanta: real cost per property by step vs. the revenue of its credit (EUR)';

    public function handle(ImoplantaCustos $custos): int
    {
        $linhas = $custos->porImovel();
        $r = $custos->resumo($linhas);

        $this->table(['Plano', 'Imóveis', 'Receita méd. €', 'Custo méd. €', 'Margem méd. €'],
            collect($r['tiers'])->map(fn ($t, $plano) => [$plano, $t['imoveis'], $t['receita'], $t['custo'], $t['margem']])->values()
                ->push(['TODOS', $r['media']['imoveis'], $r['media']['receita'], $r['media']['custo'], $r['media']['margem']])->all());
        $this->table(['Passo', 'Custo médio por imóvel €'], collect($r['passos'])->map(fn ($v, $k) => [$k, $v])->values()->all());

        if ($this->option('imoveis')) {
            $this->table(['#', 'Imóvel', 'Plano', 'Refeitos 3D/img', ...ImoplantaCustos::PASSOS, 'Custo €', 'Receita €', 'Margem €'],
                $linhas->map(fn ($l) => [$l['id'], $l['nome'], $l['plano'], $l['refeitos_3d'].'/'.$l['refeitos_imagens'],
                    ...array_map(fn ($s) => $l['passos'][$s] ?? 0, ImoplantaCustos::PASSOS), $l['custo'], $l['receita'], $l['margem']])->all());
        }

        return self::SUCCESS;
    }
}
