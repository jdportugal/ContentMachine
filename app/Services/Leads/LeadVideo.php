<?php

namespace App\Services\Leads;

use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use Illuminate\Support\Facades\DB;

/**
 * A lead's clip, wherever it lives: leads come in without a session, so their
 * project is activated from the lead row before the clip is looked up.
 */
class LeadVideo
{
    public function __construct(private ProjectRepository $projects, private ProjectActivator $activator) {}

    public function lead(int $id): ?object
    {
        return DB::table('leads')->find($id);
    }

    public function clip(object $lead): ?ClipRecord
    {
        $projeto = $this->projects->find($lead->projeto);
        if (! $projeto || ! $lead->clip_id) {
            return null;
        }
        $this->activator->activate($projeto);

        return app(ClipStore::class)->find($lead->clip_id);
    }

    /**
     * The lead's free video, clean (bought, or unlocked by an invite). Usually instant: the
     * clean copy was rendered right after the free one (RenderJob $limpo). If it is still
     * rendering, it is handed over when it lands (RenderJob reads meta.pago); if there is none
     * (older videos), the clip is rendered again without the watermark. False: no clip.
     */
    public function limpar(object $lead): bool
    {
        $clip = $this->clip($lead);
        if (! $clip) {
            return false;
        }
        $meta = (array) $clip->meta;
        $limpo = (string) ($meta['limpo_path'] ?? '');
        if ($clip->isActive()) {
            // Still being made (a photos video made again with the contact): it comes out clean.
            $clip->update(['meta' => array_merge($meta, ['free_lead' => false, 'pago' => true])]);
        } elseif ($limpo !== '' && is_file($limpo)) {
            $clip->update(['output_path' => $limpo, 'meta' => array_merge($meta, ['free_lead' => false, 'pago' => true])]);
        } elseif (! empty($meta['limpo_pendente'])) {
            $clip->update(['meta' => array_merge($meta, ['pago' => true])]);
        } else {
            $clip->update(['meta' => array_merge($meta, ['free_lead' => false, 'pago' => true]), 'status' => ClipRecord::STATUS_RENDERING]);
            \App\Jobs\Clips\RenderJob::dispatch($clip->id);
        }

        return true;
    }

    /** The rendered file, or null while it is still being made (or failed). */
    public function ficheiro(object $lead): ?string
    {
        $clip = $this->clip($lead);

        return $clip && $clip->status === ClipRecord::STATUS_DONE && $clip->output_path && is_file($clip->output_path)
            ? $clip->output_path
            : null;
    }
}
