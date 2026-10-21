<?php

namespace App\Console\Commands;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Leads\LeadVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Brings the free-video leads up to date for follow-up: the agent's name and phone
 * once the listing has been read, when the reel is ready, and when it failed (or
 * the clip vanished) so a human can pick it up.
 */
class AtualizarLeads extends Command
{
    protected $signature = 'leads:atualizar';

    protected $description = 'Fill free-video leads in from their listing and mark their reels ready or failed';

    public function handle(LeadVideo $videos): int
    {
        $abertos = DB::table('leads')->whereNull('pronto_em')->whereNull('falhou_em')->whereNotNull('clip_id')->get();

        foreach ($abertos as $lead) {
            $clip = $videos->clip($lead);
            $listing = $clip?->meta['listing'] ?? [];
            $dados = array_filter([
                'nome' => $lead->nome ?: ($listing['agent'] ?? null),
                'telefone' => $lead->telefone ?: ($listing['phone'] ?? null),
            ]);

            if (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
                $dados['falhou_em'] = now();
                Log::warning('Free-video lead failed', ['lead' => $lead->id, 'anuncio' => $lead->anuncio, 'error' => $clip?->error]);
            } elseif ($videos->ficheiro($lead)) {
                $dados['pronto_em'] = now();
            }

            if ($dados !== [] && $dados != array_filter(['nome' => $lead->nome, 'telefone' => $lead->telefone])) {
                DB::table('leads')->where('id', $lead->id)->update($dados + ['updated_at' => now()]);
            }
        }

        return self::SUCCESS;
    }
}
