<?php

namespace App\Services\RealEstate;

use App\Jobs\Clips\ImportListingJob;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;

/**
 * Listing URL → a clip record + the import job that turns it into a narrated reel.
 * One way in for the studio form, the client portal and the public free-video form;
 * the caller decides the extra meta (uploads, notes, music…). Runs in the ACTIVE project.
 */
class ListingImport
{
    public function __construct(private ClipStore $clips) {}

    public function start(string $url, array $meta = []): ClipRecord
    {
        $ref = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'listing';
        $clip = $this->clips->create([
            'type' => ClipRecord::TYPE_ANIMATION,
            'input_kind' => 'audio',
            'status' => ClipRecord::STATUS_IMPORTING,
            'title' => 'Listing '.$ref,
            'meta' => ['listing' => ['url' => $url]] + $meta,
        ]);

        ImportListingJob::dispatch($clip->id, $url);

        return $clip;
    }
}
