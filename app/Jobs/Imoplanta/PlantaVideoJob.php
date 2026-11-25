<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Imoplanta\Fotografo;
use App\Services\Imoplanta\ImoplantaCustos;
use App\Services\Imoplanta\Imoplantas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/** The plan video add-on (Fotografo::plantaVideo); kie failing quietly tries again later, as the photos do. */
class PlantaVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $imovel, public int $ronda = 0)
    {
        $this->captureProject();
    }

    public function handle(Fotografo $fotografo, ImoplantaCustos $custos): void
    {
        $p = Imoplanta::findOrFail($this->imovel);
        $custos->contexto($p->id, 'furniture_video');
        try {
            $p->guardarExtra('planta', 'video', $fotografo->plantaVideo($p));
            $p->guardarExtra('planta', 'estado', 'pronto');
            $p->guardarExtra('planta', 'erro', null);
        } catch (\Throwable $e) {
            report($e);
            if ($this->ronda < FotografarJob::RONDAS) {
                self::dispatch($this->imovel, $this->ronda + 1)->delay(now()->addMinutes(5));

                return;
            }
            $p->guardarExtra('planta', 'estado', 'falhou');
            $p->guardarExtra('planta', 'erro', Str::limit($e->getMessage(), 250));
        } finally {
            $custos->limpar();
        }
        if ($p->refresh()->extra('video', 'espera') && $p->extra('planta') !== 'a_fazer') {   // a reel waiting for this: with it, or without if it failed
            app(Imoplantas::class)->continuarReel($p);
        }
    }
}
