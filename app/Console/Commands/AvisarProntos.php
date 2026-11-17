<?php

namespace App\Console\Commands;

use App\Services\Leads\AvisoPronto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The "vídeo pronto" email for videos made before it existed: lists the free videos that are
 * ready, not bought, never emailed and have an address (internal tests left out); --enviar sends.
 */
class AvisarProntos extends Command
{
    protected $signature = 'leads:avisar-prontos {--desde=1 : first lead id} {--enviar : send (otherwise only list)}';

    protected $description = 'Send the "o seu vídeo está pronto" email to earlier free videos that never got it';

    public function handle(AvisoPronto $aviso): int
    {
        $leads = DB::table('leads')
            ->where('id', '>=', (int) $this->option('desde'))
            ->whereNotNull('pronto_em')->whereNull('falhou_em')->whereNull('aviso_pronto_em')
            ->whereNull('pago_em')->whereNull('oferta_em')
            ->where(fn ($q) => $q->whereNull('origem')->orWhere('origem', 'not like', '%teste-interno%'))
            ->orderBy('id')->get()
            ->filter(fn ($l) => AvisoPronto::destino($l) !== null);

        foreach ($leads as $l) {
            $this->line("#{$l->id} {$l->agencia} ".AvisoPronto::destino($l)." (pronto {$l->pronto_em})");
        }
        if (! $this->option('enviar')) {
            $this->info($leads->count().' por enviar. Use --enviar para enviar.');

            return self::SUCCESS;
        }
        $n = 0;
        foreach ($leads as $l) {
            $n += $aviso->enviar($l) ? 1 : 0;
        }
        $this->info("$n emails enviados.");

        return self::SUCCESS;
    }
}
