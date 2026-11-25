<?php

namespace App\Jobs\Imoplanta;

use App\Jobs\Concerns\RunsInProject;
use App\Models\Imoplanta;
use App\Services\Imoplanta\ImoplantaCustos;
use App\Services\Imoplanta\Walkthrough;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The walkthrough: every photo's clip is submitted first (they render in parallel, a few minutes each), then each
 * is collected and kept (video_clips: a retry never pays twice), then they are joined. A clip that fails is left
 * out; the video fails only if none came back.
 */
class VideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $imovel)
    {
        $this->captureProject();
        $this->onQueue('media');
    }

    public function handle(Walkthrough $walk, KieClient $kie, ImoplantaCustos $custos): void
    {
        $p = Imoplanta::with('imagens')->findOrFail($this->imovel);
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $custos->contexto($p->id, 'video');
        $kie->passo = 'imoplanta_imagens';
        try {
            $ordem = $walk->ordem($p);
            $feitos = array_filter((array) $p->video_clips, fn ($f) => $disk->exists($f));
            $tarefas = [];
            foreach ($ordem as $img) {
                if (! isset($feitos[$img->id])) {
                    $url = $kie->upload($disk->get($img->foto), 'foto.png');
                    $tarefas[$img->id] = $kie->submitVideo($walk->prompt($walk->tipo($p, $img)), $url);
                }
            }
            foreach ($tarefas as $id => $tarefa) {
                try {
                    $f = $p->pasta().'/video/clip-'.$id.'-'.Str::random(6).'.mp4';
                    $disk->put($f, Http::timeout(300)->get($kie->pollVideo($tarefa))->throw()->body());
                    $feitos[$id] = $f;
                    $p->update(['video_clips' => $feitos]);
                } catch (\Throwable $e) {
                    report($e);                       // this room is left out of the video
                }
            }
            $clips = array_values(array_filter(array_map(fn ($img) => isset($feitos[$img->id]) ? $disk->path($feitos[$img->id]) : null, $ordem)));
            if (! $clips) {
                throw new \RuntimeException('Nenhum dos clips ficou pronto.');
            }
            $destino = $p->pasta().'/video/walkthrough-'.Str::random(8).'.mp4';
            @mkdir(dirname($disk->path($destino)), 0775, true);
            $walk->juntar($clips, $disk->path($destino));
            if ($p->video && $p->video !== $destino) {
                $disk->delete($p->video);
            }
            $p->update(['video' => $destino, 'video_estado' => 'pronto', 'video_erro' => null]);
            app(\App\Services\Imoplanta\Imoplantas::class)->fazerPrevias($p->refresh());   // the add-ons' real, watermarked previews
        } catch (\Throwable $e) {
            report($e);
            $p->update(['video_estado' => 'falhou', 'video_erro' => Str::limit($e->getMessage(), 250)]);
        } finally {
            $custos->limpar();
        }
    }
}
