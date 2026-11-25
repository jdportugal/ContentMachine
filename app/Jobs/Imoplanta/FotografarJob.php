<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\ImoplantaImagem;
use App\Services\Imoplanta\Fotografo;
use App\Services\Imoplanta\ImoplantaCustos;
use App\Services\Imoplanta\Imoplantas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/** One browser render → photoreal image (Nano Banana), or one AI change to an image ($refazer; a failure gives it back). */
class FotografarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 600;

    /** Rounds after the first when kie keeps failing (each round already tries two models twice), 5 minutes apart. */
    public const RONDAS = 2;

    /** $refazer: apply the customer's change to the photo; $novamente: a fresh photo from the render again; $ronda: which retry round */
    public function __construct(public int $imagem, public bool $refazer, public bool $novamente = false, public int $ronda = 0)
    {
        $this->captureProject();
    }

    public function handle(Fotografo $fotografo, ImoplantaCustos $custos, Imoplantas $imoplantas): void
    {
        $img = ImoplantaImagem::with('imoplanta')->findOrFail($this->imagem);
        $custos->contexto($img->imoplanta_id, $this->refazer || $this->novamente ? 'image_redo' : 'image_generate');
        try {
            $foto = $this->refazer ? $fotografo->alterar($img, (string) $img->pedido) : $fotografo->fotografar($img->imoplanta, $img);
            $img->update(['foto' => $foto, 'estado' => ImoplantaImagem::PRONTA, 'erro' => null]);
        } catch (\Throwable $e) {
            report($e);
            if ($this->ronda < self::RONDAS) {   // quietly, later: the image stays «a fotografar» / «a alterar»
                self::dispatch($this->imagem, $this->refazer, $this->novamente, $this->ronda + 1)->delay(now()->addMinutes(5));

                return;
            }
            $this->refazer
                ? $imoplantas->devolverAlteracaoImagem($img, 'Não conseguimos fazer esta alteração. Não foi descontada.')
                : $img->update(['estado' => ImoplantaImagem::FALHOU, 'erro' => Str::limit($e->getMessage(), 250)]);
        } finally {
            $custos->limpar();
        }
    }
}
