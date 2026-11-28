<?php

namespace App\Jobs\Ferramentas;

use App\Jobs\Concerns\RunsInProject;
use App\Models\FerramentaIa;
use App\Services\Ferramentas\Ferramentas;
use App\Services\Costs\CostLedger;
use App\Services\Ferramentas\Analista;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** The check of a newly uploaded photo. If it can't run, the customer simply sees no verdict: never a block. */
class AnalisarFotoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $video, public string $original)
    {
        $this->captureProject();
    }

    public function handle(Analista $analista, CostLedger $custos): void
    {
        $f = FerramentaIa::find($this->video);
        if (! $f || $f->original !== $this->original) {   // the photo was replaced meanwhile: its own job checks it
            return;
        }
        $custos->contexto('ferramenta', (string) $f->id, ['passo' => 'analise']);
        try {
            $analise = $analista->analisar($f);
        } catch (\Throwable $e) {
            Ferramentas::registarFalha('análise da fotografia', $f, $e);
            $analise = ['estado' => 'falhou'];
        } finally {
            $custos->contexto(null, null);
        }
        FerramentaIa::whereKey($f->id)->where('original', $this->original)->update(['analise' => json_encode($analise, JSON_UNESCAPED_UNICODE)]);
    }
}
