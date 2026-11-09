<?php

namespace App\Console\Commands;

use App\Services\Contas\AmostrasDeEfeitos;
use App\Services\Contas\EdicaoDeVideo;
use App\Services\Themes\ThemeContext;
use App\Services\Themes\ThemeRepository;
use Illuminate\Console\Command;

/** Queue the sample of every picture effect in every theme (or those given), so the editor never waits. */
class PrepararAmostras extends Command
{
    protected $signature = 'imocena:amostras {temas?* : só estes temas}';

    protected $description = 'Prepara as amostras dos efeitos que o cliente pode escolher no editor';

    public function handle(ThemeRepository $temas, ThemeContext $contexto): int
    {
        $pedidos = (array) $this->argument('temas');
        if (AmostrasDeEfeitos::fotos() === []) {
            $this->error('Sem fotos de demonstração em '.config('contentmachine.amostras.fotos', public_path('media/demo/moradia-lajeosa')));

            return self::FAILURE;
        }
        foreach ($temas->all() as $tema) {
            if ($pedidos !== [] && ! in_array($tema->slug, $pedidos, true)) {
                continue;
            }
            $contexto->use($tema->slug);
            $efeitos = array_keys(EdicaoDeVideo::efeitosDeFoto());
            $faltam = array_values(array_filter($efeitos, fn ($s) => ! AmostrasDeEfeitos::existe($s)));
            foreach ($faltam as $slug) {
                AmostrasDeEfeitos::pedir($slug);
            }
            $this->line(sprintf('%-22s %2d efeitos, %2d em fila', $tema->slug, count($efeitos), count($faltam)));
        }

        return self::SUCCESS;
    }
}
