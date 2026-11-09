<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Contas\AmostrasDeEfeitos;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Renders the customer-facing sample of one picture effect, in the theme it was queued in. */
class RenderAmostraJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 600;

    public function __construct(public string $slug)
    {
        $this->captureProject();
        $this->onQueue('previews');
    }

    public function handle(RemotionRenderer $renderer): void
    {
        $this->activateProject();
        $out = AmostrasDeEfeitos::caminho($this->slug);
        $plano = AmostrasDeEfeitos::plano($this->slug);
        if (is_file($out) || ! $plano) {
            return;
        }
        @mkdir(dirname($out), 0775, true);
        // Rendered aside, then moved in: a half-written file is never served.
        $tmp = dirname($out).'/.'.basename($out, '.mp4').'-'.uniqid().'.mp4';
        $renderer->render($plano, $tmp);
        rename($tmp, $out);
    }
}
