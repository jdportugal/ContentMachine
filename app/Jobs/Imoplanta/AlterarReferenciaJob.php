<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Imoplanta\Fotografo;
use App\Services\Imoplanta\ImoplantaCustos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * Step 2: the owner's change to the finishes board ($nivel null) or to one storey's 3D floor plan. A new board
 * redraws every storey's floor plan from its render with it, so they match (not counted as changes). A change
 * that can't be made is given back.
 */
class AlterarReferenciaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $imovel, public ?int $nivel, public string $pedido)
    {
        $this->captureProject();
    }

    public function handle(Fotografo $fotografo, ImoplantaCustos $custos): void
    {
        $p = Imoplanta::findOrFail($this->imovel);
        $custos->contexto($p->id, 'references');
        try {
            if ($this->nivel === null) {
                $novo = $fotografo->alterarQuadro($p, $this->pedido);
                $p->refresh()->update(['acabamentos_img' => $novo, 'quadro_estado' => null, 'quadro_erro' => null,
                    'acabamentos_pedidos' => [...($p->acabamentos_pedidos ?? []), $this->pedido]]);
                foreach ($p->niveisComPlanta() as $n) {   // the floor plans follow the new board
                    if (! $p->aereo($n, 'render')) {
                        continue;
                    }
                    foreach (['img', 'lote', 'construcao'] as $k) {
                        $p->guardarAereo($n, $k, null);
                    }
                    $p->guardarAereo($n, 'estado', 'a_fazer');
                    $ok = $fotografo->aereo($p->refresh(), $novo, 'references', $n);
                    $p->guardarAereo($n, 'estado', $ok ? null : 'falhou');
                }
            } else {
                $novo = $fotografo->alterarAereo($p, $this->nivel, $this->pedido);
                foreach (['lote', 'construcao'] as $k) {   // the plan video's clip was of the old plan
                    $p->guardarAereo($this->nivel, $k, null);
                }
                $p->guardarAereo($this->nivel, 'img', $novo);
                $p->guardarAereo($this->nivel, 'estado', null);
            }
        } catch (\Throwable $e) {
            report($e);
            if ($this->nivel === null) {
                DB::table('imoplantas')->where('id', $p->id)->where('acabamentos_refeitos', '>', 0)->decrement('acabamentos_refeitos');
                $p->refresh()->update(['quadro_estado' => null, 'quadro_erro' => 'Não conseguimos fazer esta alteração. Não foi descontada.']);
            } else {
                $p->refresh()->guardarAereo($this->nivel, 'refeitos', max(0, (int) (($p->aereos[$this->nivel] ?? [])['refeitos'] ?? 1) - 1));
                $p->guardarAereo($this->nivel, 'estado', null);
                $p->guardarAereo($this->nivel, 'erro', 'Não conseguimos fazer esta alteração. Não foi descontada.');
            }
        } finally {
            $custos->limpar();
        }
    }
}
