<?php

namespace App\Console\Commands;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Leads\LeadVideo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * A customer pays for videos, not attempts: when a paid reel fails (or its clip
 * is gone), the video goes back to the purchase it came from. A reel that renders
 * is simply marked finished, so it is not looked at again.
 */
class DevolverVideos extends Command
{
    protected $signature = 'videos:devolver';

    protected $description = 'Give failed paid videos back to their purchase';

    public function handle(LeadVideo $clips): int
    {
        foreach (DB::table('videos')->whereNull('terminado_em')->get() as $video) {
            $clip = $clips->clip($video);   // activates the video's project first
            if ($clip && $clip->status === ClipRecord::STATUS_DONE) {
                DB::table('videos')->where('id', $video->id)->update(['terminado_em' => now()]);
            } elseif (! $clip || $clip->status === ClipRecord::STATUS_FAILED) {
                DB::transaction(function () use ($video) {
                    DB::table('videos')->where('id', $video->id)->update(['terminado_em' => now(), 'devolvido_em' => now()]);
                    DB::table('compras')->where('id', $video->compra_id)->whereNull('reembolsado_em')->increment('restantes', 1, ['updated_at' => now()]);
                });
            }
        }

        return self::SUCCESS;
    }
}
