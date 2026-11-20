<?php

namespace App\Console\Commands;

use App\Services\Leads\LembreteConvite;
use Illuminate\Console\Command;

/** The invite reminder, two days after a free video (see LembreteConvite). Lists by default; --enviar sends. */
class LembrarConvite extends Command
{
    protected $signature = 'leads:lembrar-convite {--enviar : send (otherwise only list)}';

    protected $description = 'Remind agents with a free video they can get it clean by inviting a colleague';

    public function handle(LembreteConvite $lembrete): int
    {
        $leads = $lembrete->devidos();
        foreach ($leads as $l) {
            $this->line("#{$l->id} {$l->agencia} {$l->email} (pronto {$l->pronto_em})");
        }
        if (! $this->option('enviar')) {
            $this->info($leads->count().' por enviar. Use --enviar para enviar.');

            return self::SUCCESS;
        }
        $n = 0;
        foreach ($leads as $l) {
            $n += rescue(fn () => $lembrete->enviar($l), false, report: true) ? 1 : 0;
        }
        $this->info("$n emails enviados.");

        return self::SUCCESS;
    }
}
