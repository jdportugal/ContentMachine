<?php

namespace App\Jobs\Avatars;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Avatars\AvatarStore;
use App\Services\Avatars\FalLipsync;
use App\Services\Clips\FfmpegVideoCompositor;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Costs\CostLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Runs one Avatar Gen lip-sync: hands the uploaded video + audio to fal.ai
 * (Sync Lipsync 2 Pro), writes the finished video into the project's vault and
 * records what it cost.
 *
 * fal bills this model per minute of the result, so the price is only known
 * once the video is here — it is measured off the finished file rather than
 * guessed from the inputs.
 */
class GenerateLipsyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 1800;

    /** One shot: a blind retry would upload and pay for the whole thing again.
     *  Recovery goes through the stored request handle instead — see below. */
    public int $tries = 1;

    public function __construct(public string $avatarId)
    {
        $this->captureProject();
    }

    public function handle(AvatarStore $store, FalLipsync $fal, FfmpegVideoCompositor $probe, CostLedger $ledger): void
    {
        $this->activateProject();

        $avatar = $store->find($this->avatarId);
        if (! $avatar) {
            return;
        }

        $ledger->contexto('avatar', $avatar->id());

        $disk = Storage::disk('local');
        $video = $disk->path((string) $avatar->get('video_path'));
        $audio = $disk->path((string) $avatar->get('audio_path'));
        $tmp = null;

        try {
            if (! $fal->configured()) {
                throw new \RuntimeException('No fal.ai key — add one under Settings → API keys.');
            }

            // Submit once, and write the request handle down BEFORE the long wait.
            // fal bills from acceptance, so anything that interrupts the wait — a
            // dropped wifi, a closed lid, a killed worker — must leave behind
            // enough to collect the result instead of paying for a second run.
            // A record that already carries one is a resume: it skips straight to
            // collecting, and never uploads or submits again.
            $pedido = $avatar->get('fal_request');
            if (! is_array($pedido) || ($pedido['status_url'] ?? '') === '') {
                foreach ([$video, $audio] as $in) {
                    if (! is_file($in)) {
                        throw new \RuntimeException('The uploaded file is gone: '.basename($in));
                    }
                }
                $pedido = $fal->submit($video, $audio);
                $avatar->update(['fal_request' => $pedido]);
            }

            // Collect to a temp file first: a half-downloaded video must never be
            // servable as this record's finished asset.
            $tmp = tempnam(sys_get_temp_dir(), 'lipsync_').'.mp4';
            $fal->collect($pedido, $tmp);

            $segundos = $probe->probeDuration($tmp);

            // What fal itself says it billed, in minutes. Only when it will not
            // say do we fall back to measuring the video — and a duration we
            // could not measure must NOT read as a free generation, so the record
            // carries null (shown as "cost not measured") rather than $0.
            $unidades = $fal->billableUnits((string) ($pedido['request_id'] ?? ''));
            $custo = $unidades !== null
                ? round($unidades * (float) config('contentmachine.custos.fal_lipsync_min', 5.00), 5)
                : self::preco($segundos);

            $out = $store->videoPath($avatar->id());
            @mkdir(dirname($out), 0775, true);
            if (! @rename($tmp, $out)) {
                copy($tmp, $out);
                @unlink($tmp);
            }
            $tmp = null;

            $ledger->registar(
                'fal_lipsync',
                $custo,
                config('services.fal.lipsync_model').' · '.round($segundos, 1).'s'.($unidades === null ? ' (estimated)' : ''),
            );

            $avatar->update([
                'status' => EffectRecord::STATUS_ACTIVE,
                'duration' => $segundos > 0 ? round($segundos, 2) : null,
                'cost' => ($unidades !== null || $segundos > 0) ? $custo : null,
                // Flagged so the page never presents a guess as a fact.
                'cost_estimated' => $unidades === null,
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            if ($tmp) {
                @unlink($tmp);
            }
            $avatar->update([
                'status' => EffectRecord::STATUS_FAILED,
                'error' => Str::limit($e->getMessage(), 500),
            ]);
        } finally {
            // The inputs only ever existed to reach fal. Once the request is in
            // fal's queue they are dead weight — a resume works off the request
            // handle, not off these files.
            if (is_array($avatar->refresh()->get('fal_request'))) {
                foreach (['video_path', 'audio_path'] as $k) {
                    $disk->delete((string) $avatar->get($k));
                }
            }
            $ledger->contexto(null, null);
        }
    }

    /**
     * Fallback estimate, used only when fal will not report its billable units:
     * per minute of video, with the seconds rounded UP — which is how fal was
     * observed to bill (a 9.26s result billed as 10s).
     */
    public static function preco(float $segundos): float
    {
        $porMinuto = (float) config('contentmachine.custos.fal_lipsync_min', 5.00);

        return round(ceil(max(0.0, $segundos)) / 60 * $porMinuto, 5);
    }
}
