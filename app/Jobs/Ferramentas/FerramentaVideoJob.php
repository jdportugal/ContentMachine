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

/** A video's clip from its approved frames, or a new version of it ($refazer). A failure keeps what was there and isn't counted. */
class FerramentaVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public int $video, public bool $refazer)
    {
        $this->captureProject();
    }

    public function handle(Realizador $realizador, Ferramentas $ferramentas, CostLedger $custos): void
    {
        $f = FerramentaIa::findOrFail($this->video);
        $custos->contexto('ferramenta', (string) $f->id, ['passo' => $this->refazer ? 'video_redo' : 'video']);
        try {
            $video = $realizador->animar($f, $this->refazer ? $f->pedido : null);
            $f->update(['video' => $video, 'estado' => FerramentaIa::PRONTO, 'erro' => null]);
        } catch (\Throwable $e) {
            report($e);
            $ferramentas->devolverVideo($f, $this->refazer, 'Não conseguimos criar o vídeo. Não foi descontado; pode tentar de novo.');
        } finally {
            $custos->contexto(null, null);
        }
    }
}
