<?php

namespace App\Services\Clips;

use App\Jobs\Clips\RenderBackgroundSampleJob;
use App\Services\Clips\Store\BackgroundStore;
use App\Services\Clips\Store\EffectRecord;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Export/import of clip BACKGROUNDS so a backdrop can move between projects/installs
 * or be shared. The file is self-contained JSON: a code background carries its full
 * Remotion component (renders on import with no AI call); a video background carries
 * its mp4 (base64). Mirrors EffectPortability.
 */
class BackgroundPortability
{
    private const TYPE = 'brand-machine/background';

    public function __construct(
        private readonly BackgroundStore $store,
        private readonly BackgroundLibrary $library,
    ) {}

    /**
     * Build the export payload. $id is a background id or 'all' (every active
     * background). Returns null when there is nothing to export.
     *
     * @return array<string,mixed>|null
     */
    public function export(string $id): ?array
    {
        $entries = [];
        if ($id === 'all') {
            foreach ($this->store->active()->values() as $b) {
                $entries[] = $this->exportOne($b);
            }
        } elseif ($rec = $this->store->find($id)) {
            $entries[] = $this->exportOne($rec);
        }

        if ($entries === []) {
            return null;
        }

        return [
            'type' => self::TYPE,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'backgrounds' => $entries,
        ];
    }

    /** @return array<string,mixed> */
    private function exportOne(EffectRecord $b): array
    {
        $out = [
            'kind' => (string) $b->kind,
            'slug' => (string) $b->slug,
            'display_name' => (string) $b->display_name,
            'description' => (string) ($b->description ?? ''),
            'prompt' => (string) ($b->prompt ?? ''),
            'tsx' => (string) ($b->tsx ?? ''),
        ];

        if ($b->kind === BackgroundStore::KIND_VIDEO) {
            $video = $this->store->videoPath($b->id());
            if (is_file($video)) {
                // ponytail: base64 inflates ~33%; huge mp4s can exceed upload limits
                // on import. Fine for typical backdrops; the common case is code.
                $out['video'] = ['ext' => 'mp4', 'data' => base64_encode((string) file_get_contents($video))];
            }
        }

        return $out;
    }

    /**
     * Recreate the backgrounds from an export payload into the active project. Each
     * gets a fresh id and a collision-free slug and is made active. Code backgrounds
     * are promoted (component written, index rebuilt) and get a sample render queued;
     * video backgrounds have their mp4 written back. Returns how many were imported.
     *
     * @param  array<string,mixed>  $payload
     */
    public function import(array $payload): int
    {
        if (($payload['type'] ?? null) !== self::TYPE || ! is_array($payload['backgrounds'] ?? null)) {
            throw new RuntimeException('This is not a Brand Machine background export file.');
        }

        $count = 0;
        foreach ($payload['backgrounds'] as $entry) {
            if (! is_array($entry) || blank($entry['slug'] ?? null)) {
                continue;
            }
            $isVideo = ($entry['kind'] ?? BackgroundStore::KIND_CODE) === BackgroundStore::KIND_VIDEO;
            $slug = $this->uniqueSlug(Str::slug((string) $entry['slug']));

            if ($isVideo) {
                if (blank($entry['video']['data'] ?? null)) {
                    continue;
                }
                $rec = $this->store->create([
                    'kind' => BackgroundStore::KIND_VIDEO,
                    'slug' => $slug,
                    'display_name' => (string) ($entry['display_name'] ?? $slug),
                    'description' => (string) ($entry['description'] ?? ''),
                    'status' => EffectRecord::STATUS_ACTIVE,
                ]);
                $target = $this->store->videoPath($rec->id());
                @mkdir(dirname($target), 0775, true);
                file_put_contents($target, base64_decode((string) $entry['video']['data']));
                $count++;

                continue;
            }

            if (blank($entry['tsx'] ?? null)) {
                continue;
            }
            $rec = $this->store->create([
                'kind' => BackgroundStore::KIND_CODE,
                'slug' => $slug,
                'display_name' => (string) ($entry['display_name'] ?? $slug),
                'description' => (string) ($entry['description'] ?? ''),
                'prompt' => (string) ($entry['prompt'] ?? ''),
                'tsx' => (string) $entry['tsx'],
                'status' => EffectRecord::STATUS_ACTIVE,
            ]);
            $this->library->promote($rec); // write backgrounds/<slug>.tsx, activate, rebuild index
            RenderBackgroundSampleJob::dispatch($slug);
            $count++;
        }

        return $count;
    }

    /** A slug that clashes with no existing background. */
    private function uniqueSlug(string $desired): string
    {
        $desired = $desired !== '' ? $desired : 'background';
        $slug = $desired;
        $n = 1;
        while ($this->store->slugExists($slug)) {
            $slug = $desired.'-'.(++$n);
        }

        return $slug;
    }
}
