<?php

namespace App\Services\Clips\Contracts;

interface AnimationPlanner
{
    /**
     * Produce an (unvalidated) animation plan for a transcript.
     *
     * @param  array  $transcript  transcript shape {duration,text,words,segments}
     * @param  'dense'|'sparse'  $mode
     * @return array the plan (unvalidated) — see plan shape
     */
    public function plan(array $transcript, string $mode, array $options = []): array;
}
