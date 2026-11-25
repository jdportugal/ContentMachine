<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Imoplanta\ImoplantaCustos;
use App\Services\Imoplanta\Imoplantas;
use App\Services\Imoplanta\LeitorPlantas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/** A customer's change to the 3D model: an LLM edits the house description (the page rebuilds the 3D view from it). A failure gives the re-do back. */
class AlterarModeloJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public int $imovel, public string $pedido)
    {
        $this->captureProject();
    }

    public function handle(LeitorPlantas $leitor, ImoplantaCustos $custos, Imoplantas $imoplantas): void
    {
        $p = Imoplanta::findOrFail($this->imovel);
        $custos->contexto($p->id, 'ai_change_3d');
        try {
            $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
            $casa = $leitor->alterar($p->casa, $this->pedido, array_map(fn ($f) => $disk->path($f), $p->imagensParaLer()), $p->contextoPlantas());
            $p->update(['casa' => $casa, 'estado' => Imoplanta::MODELO, 'erro' => null, 'versao' => $p->versao + 1]);
        } catch (\Throwable $e) {
            report($e);
            $imoplantas->devolverAlteracao($p, 'Não conseguimos aplicar a alteração. Não foi descontada. Tente descrevê-la de outra forma.');
        } finally {
            $custos->limpar();
        }
    }
}
