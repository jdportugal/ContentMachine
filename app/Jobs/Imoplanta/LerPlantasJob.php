<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Contas\LinkDeAcesso;
use App\Services\Imoplanta\ImoplantaCasa;
use App\Services\Imoplanta\ImoplantaCustos;
use App\Services\Imoplanta\LeitorPlantas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Step 1: an LLM reads the plan images into the house description — which is the 3D model (built in the browser). */
class LerPlantasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 1200;

    public function __construct(public int $imovel)
    {
        $this->captureProject();
    }

    public function handle(LeitorPlantas $leitor, ImoplantaCustos $custos): void
    {
        $p = Imoplanta::findOrFail($this->imovel);
        $custos->contexto($p->id, 'extract');
        try {
            $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
            $casa = $leitor->ler(array_map(fn ($f) => $disk->path($f), $p->imagensParaLer()), $p->contextoPlantas());
            $p->update([
                'casa' => $casa, 'estado' => Imoplanta::MODELO, 'erro' => null, 'versao' => $p->versao + 1,
                'cameras' => $p->cameras ?: ImoplantaCasa::camerasIniciais($casa, $p->tipologia),
            ]);
            $this->avisar($p);
        } catch (\Throwable $e) {
            report($e);
            $p->update(['estado' => Imoplanta::FALHOU, 'erro' => Str::limit($e->getMessage(), 250)]);
        } finally {
            $custos->limpar();
        }
    }

    /** Tells a customer by email that the model is ready, with a link that signs them in on it (3 days, once). */
    private function avisar(Imoplanta $p): void
    {
        $user = $p->user;
        if (! $user || $user->is_admin) {
            return;
        }
        // The site's sign-in email (never throws), straight to the property; the link lasts 3 days.
        app(LinkDeAcesso::class)->enviar($user, 'O modelo 3D de «'.$p->nome.'» está pronto',
            'O modelo 3D de «'.$p->nome.'» está pronto. Já pode vê-lo, verificar as divisões e escolher onde ficam as câmaras das fotografias. O link entra na sua conta e vale 3 dias.',
            '/imoplanta/'.$p->id, 60 * 24 * 3);
    }
}
