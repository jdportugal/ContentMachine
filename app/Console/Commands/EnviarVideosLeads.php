<?php

namespace App\Console\Commands;

use App\Mail\VideoGratisPronto;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Leads\LeadVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * Emails each free-video lead the link to their reel once it is rendered; a
 * failed (or vanished) clip is marked so a human can follow it up.
 */
class EnviarVideosLeads extends Command
{
    protected $signature = 'leads:enviar';

    protected $description = 'Email free-video leads whose reel is ready';

    public function handle(LeadVideo $videos): int
    {
        $pendentes = DB::table('leads')->whereNull('enviado_em')->whereNull('falhou_em')->whereNotNull('clip_id')->get();

        foreach ($pendentes as $lead) {
            $clip = $videos->clip($lead);

            if (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
                DB::table('leads')->where('id', $lead->id)->update(['falhou_em' => now(), 'updated_at' => now()]);
                Log::warning('Free-video lead failed', ['lead' => $lead->id, 'email' => $lead->email, 'error' => $clip?->error]);

                continue;
            }
            if ($videos->ficheiro($lead) === null) {
                continue;   // still being made
            }

            Mail::to($lead->email)->send(new VideoGratisPronto($lead->nome, URL::signedRoute('lead.video', ['lead' => $lead->id])));
            DB::table('leads')->where('id', $lead->id)->update(['enviado_em' => now(), 'updated_at' => now()]);
            $this->info("Sent lead {$lead->id} to {$lead->email}");
        }

        return self::SUCCESS;
    }
}
