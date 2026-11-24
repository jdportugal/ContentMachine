<?php

namespace App\Console\Commands;

use App\Services\Alertas\DemoDoImovel;
use Illuminate\Console\Command;

/** Make (or find) the demo video of one listing, as the new-listing emails do, and print its page. */
class DemoDeImovel extends Command
{
    protected $signature = 'alertas:demo {url : Link do anúncio}';

    protected $description = 'Make the cheap demo video of a listing (its photos + the recorded demo voice) and print its page';

    public function handle(DemoDoImovel $demos): int
    {
        if (! DemoDoImovel::voz()) {
            $this->error('Sem a voz da demonstração em '.config('contentmachine.alertas.demo_voz'));

            return self::FAILURE;
        }
        $demo = $demos->gerar((string) $this->argument('url'));
        if (! $demo) {
            $this->error('Não foi possível fazer a demonstração (anúncio sem fotos suficientes ou ilegível). Ver o log.');

            return self::FAILURE;
        }
        $this->line($demo['pagina']);
        $this->line($demo['video']);

        return self::SUCCESS;
    }
}
