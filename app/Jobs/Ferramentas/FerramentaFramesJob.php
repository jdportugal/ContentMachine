<?php

namespace App\Jobs\Ferramentas;

use App\Jobs\Concerns\RunsInProject;
use App\Models\FerramentaIa;
use App\Services\Costs\CostLedger;
use App\Services\Ferramentas\Ferramentas;
use App\Services\Ferramentas\Realizador;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A video's AI frame: the first (the customer's photo is the other frame) or a change to it ($alteracao). The
 * first failing for good gives the video back; a failed change isn't counted. kie's own text is never shown.
 */
class FerramentaFramesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $video, public bool $alteracao)
    {
        $this->captureProject();
    }

    public function handle(Realizador $realizador, Ferramentas $ferramentas, CostLedger $custos): void
    {
        $f = FerramentaIa::findOrFail($this->video);
        $custos->contexto('ferramenta', (string) $f->id, ['passo' => $this->alteracao ? 'frames_edit' : 'frames']);
        try {
            $quadro = $realizador->enquadrar($f, $this->alteracao ? (string) $f->pedido : null);
            $outro = $f->quadroIa() === 'fim' ? 'inicio' : 'fim';
            $f->update([
                $f->quadroIa() => $quadro, $outro => $f->{$outro} ?: $f->original,
                'versoes' => [...($f->versoes ?? []), $quadro], 'estado' => FerramentaIa::A_APROVAR, 'erro' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->alteracao
                ? $ferramentas->devolverAlteracao($f, 'Não conseguimos fazer esta alteração. Não foi descontada; pode pedir de novo.')
                : $ferramentas->devolver($f, 'Não conseguimos criar as imagens. O vídeo foi devolvido à sua conta; pode tentar de novo.');
        } finally {
            $custos->contexto(null, null);
        }
    }
}
