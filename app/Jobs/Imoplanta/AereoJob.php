<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Imoplanta\Acabamentos;
use App\Services\Imoplanta\Fotografo;
use App\Services\Imoplanta\ImoplantaCustos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** One storey's realistic top view (3D floor plan), made as soon as its browser render arrives. */
class AereoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public int $imovel, public int $nivel)
    {
        $this->captureProject();
    }

    public function handle(Fotografo $fotografo, Acabamentos $acabamentos, ImoplantaCustos $custos): void
    {
        $p = Imoplanta::findOrFail($this->imovel);
        $custos->contexto($p->id, 'references');
        try {
            $ok = $fotografo->aereo($p, $acabamentos->garantir($p, 'references'), 'references', $this->nivel);   // null on failure: the photos go without it
            $p->refresh()->guardarAereo($this->nivel, 'estado', $ok ? null : 'falhou');
        } finally {
            $custos->limpar();
        }
    }
}
