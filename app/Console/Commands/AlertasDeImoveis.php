<?php

namespace App\Console\Commands;

use App\Services\Alertas\VigiarConsultores;
use Illuminate\Console\Command;

/** Hourly: read the public pages of the agents who asked for new-listing alerts, and email them about listings that are new. */
class AlertasDeImoveis extends Command
{
    protected $signature = 'alertas:imoveis';

    protected $description = 'Check watched agents\' pages for new listings and email them a link to make the video';

    public function handle(VigiarConsultores $vigiar): int
    {
        $novos = $vigiar->sincronizar();
        $r = $vigiar->verificar();
        $this->info("Consultores novos: {$novos} · páginas lidas: {$r['lidos']} · avisos enviados: {$r['avisos']} · falhas: {$r['falhas']}");

        return self::SUCCESS;
    }
}
