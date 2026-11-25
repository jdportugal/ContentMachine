<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Imoplanta\Acabamentos;
use App\Services\Imoplanta\ImoplantaCustos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Step 2: the finishes board, made as soon as the cameras are confirmed. */
class QuadroJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public int $imovel)
    {
        $this->captureProject();
    }

    public function handle(Acabamentos $acabamentos, ImoplantaCustos $custos): void
    {
        $p = Imoplanta::findOrFail($this->imovel);
        try {
            $ok = $acabamentos->garantir($p, 'references');   // null when it can't be made (each try already used two models twice)
            $p->refresh()->update(['quadro_estado' => $ok ? null : 'falhou', 'quadro_erro' => $ok ? null : 'kie.ai: o quadro de acabamentos não foi feito.']);
        } finally {
            $custos->limpar();
        }
    }
}
