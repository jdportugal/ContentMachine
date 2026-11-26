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
 * A video's AI frames: the first ones (any frame the AI doesn't make is the customer's photo), or a change to
 * one of them ($alteracao: the frame in `alvo`). The
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
            $versoes = $f->versoes ?? [];
            $novos = [];
            // a change redoes its one frame; the first time, every AI frame in order (the end can build on the start)
            foreach ($this->alteracao ? [$f->alvo] : $f->quadrosIa() as $qual) {
                $novos[$qual] = $realizador->enquadrar($f, $qual, $this->alteracao ? (string) $f->pedido : null);
                $f->{$qual} = $novos[$qual];   // the next frame sees this one
                $versoes[$qual] = [...($versoes[$qual] ?? []), $novos[$qual]];
            }
            $fotos = array_fill_keys(array_diff(['inicio', 'fim'], $f->quadrosIa()), $f->original);   // the frame that is the photo
            $f->refresh()->update([...$fotos, ...$novos, 'versoes' => $versoes, 'estado' => FerramentaIa::A_APROVAR, 'erro' => null]);
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
