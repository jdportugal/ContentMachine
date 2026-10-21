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

    /** The rendered file, or null while it is still being made (or failed). */
    public function ficheiro(object $lead): ?string
    {
        $clip = $this->clip($lead);

        return $clip && $clip->status === ClipRecord::STATUS_DONE && $clip->output_path && is_file($clip->output_path)
            ? $clip->output_path
            : null;
    }
}
