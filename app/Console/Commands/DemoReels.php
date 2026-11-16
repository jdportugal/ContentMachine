<?php

namespace App\Console\Commands;

use App\Livewire\Landing;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\RealEstate\DemoListingScraper;
use App\Services\RealEstate\ListingImport;
use App\Services\Themes\ThemeRepository;
use Illuminate\Console\Command;

/**
 * The landing page shows each agency a video in its own template: the demo listing
 * made once per agency theme (remax-premium, iad-premium…) and saved beside the
 * general one as public/media/demo/<slug>/reel-<agency>.mp4. Without that file the
 * page falls back to reel.mp4, so this can run at any time, and again after a theme changes.
 */
class DemoReels extends Command
{
    protected $signature = 'imocena:demo-reels {agencias?* : só estas (remax, iad, exp, kw, era, century21, zome)} {--slug=moradia-lajeosa} {--espera=40 : minutos, no máximo, à espera dos vídeos}';

    protected $description = 'Faz o vídeo de demonstração no modelo de cada agência, para a página de entrada';

    public function handle(ListingImport $import, ThemeRepository $temas, ClipStore $clips): int
    {
        $slug = (string) $this->option('slug');
        $pasta = public_path("media/demo/{$slug}");
        if (! isset(DemoListingScraper::listings()[$slug]) || ! is_dir($pasta)) {
            $this->error("Sem o imóvel de demonstração em {$pasta}");

            return self::FAILURE;
        }
        $url = rtrim((string) config('app.url'), '/').'/'.DemoListingScraper::PATH.'/'.$slug;
        $pedidas = (array) $this->argument('agencias');

        $emCurso = [];
        foreach (Landing::AGENCIAS as $agencia => $a) {
            $tema = $temas->find($agencia.'-premium');
            // Every agency with a theme of its own, read or not yet: its page shows what its video will look like.
            if (! $tema || ($pedidas !== [] && ! in_array($agencia, $pedidas, true))) {
                continue;
            }
            $emCurso[$agencia] = $import->start($url, ['theme' => $tema->slug, 'demo_reel' => $agencia])->id;
            $this->line(sprintf('%-8s %s: a fazer (clip %s)', $agencia, $tema->slug, $emCurso[$agencia]));
        }

        $falhas = 0;
        $limite = now()->addMinutes(max(1, (int) $this->option('espera')));
        while ($emCurso !== [] && now()->lt($limite)) {
            sleep(15);
            foreach ($emCurso as $agencia => $id) {
                $clip = $clips->find($id);
                if (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
                    $this->error("{$agencia}: falhou".($clip?->error ? ' — '.$clip->error : ''));
                    $falhas++;
                    unset($emCurso[$agencia]);
                } elseif ($clip->status === ClipRecord::STATUS_DONE && $clip->output_path && is_file($clip->output_path)) {
                    copy($clip->output_path, "{$pasta}/reel-{$agencia}.mp4");
                    $this->info("{$agencia}: pronto → media/demo/{$slug}/reel-{$agencia}.mp4");
                    unset($emCurso[$agencia]);
                }
            }
        }
        foreach (array_keys($emCurso) as $agencia) {
            $this->warn("{$agencia}: ainda não acabou; corra o comando outra vez mais tarde.");
        }

        return $falhas === 0 && $emCurso === [] ? self::SUCCESS : self::FAILURE;
    }
}
