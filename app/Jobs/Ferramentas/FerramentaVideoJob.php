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

    public int $timeout = 3000;   // a works clip: up to 9 stage images (some drawn twice) and 10 Kling steps; < QUEUE_TIMEOUT 3300

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
            Ferramentas::registarFalha($this->refazer ? 'nova versão do vídeo' : 'vídeo', $f, $e);
            $ferramentas->devolverVideo($f, $this->refazer, 'Não conseguimos criar o vídeo. Não foi descontado; pode tentar de novo.');
        } finally {
            $custos->contexto(null, null);
        }
    }

    /** Killed (too long) or crashed outside handle(): logged, and the customer is given back what it cost, as above. */
    public function failed(\Throwable $e): void
    {
        Ferramentas::registarFalha($this->refazer ? 'nova versão do vídeo (parada)' : 'vídeo (parado)', $this->video, $e);
        if ($f = FerramentaIa::find($this->video)) {
            app(Ferramentas::class)->devolverVideo($f, $this->refazer, 'Não conseguimos criar o vídeo. Não foi descontado; pode tentar de novo.');
        }
    }
}
