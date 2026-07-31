<?php

namespace App\Services\Clips;

use App\Jobs\Clips\RenderEffectSampleJob;
use App\Services\Clips\Store\EffectRecord;
use App\Services\Clips\Store\EffectStore;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Export/import of custom SFX so a whole effect set (component source, metadata
 * and its attached sound) can move between projects/installs or be shared. The
 * file is self-contained JSON: an imported effect renders and works with no
 * further AI calls, since the Remotion component is carried inside it.
 */
class EffectPortability
{
    private const TYPE = 'brand-machine/sfx';

    public function __construct(
        private readonly EffectStore $store,
        private readonly EffectLibrary $library,
    ) {}

    /**
     * Build the export payload for one effect id, or every active custom effect
     * when $id === 'all'. Returns null when there is nothing to export.
     *
     * @return array<string,mixed>|null
     */
    public function export(string $id): ?array
    {
        $effects = $id === 'all'
            ? $this->store->active()->values()
            : collect(array_filter([$this->store->find($id)]));

        if ($effects->isEmpty()) {
            return null;
        }

        return [
            'type' => self::TYPE,
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'effects' => $effects->map(fn (EffectRecord $e) => $this->exportOne($e))->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function exportOne(EffectRecord $e): array
    {
        $out = [
            'slug' => (string) $e->slug,
            'display_name' => (string) $e->display_name,
            'description' => (string) ($e->description ?? ''),
            'prompt' => (string) ($e->prompt ?? ''),
            'tsx' => (string) $e->tsx,
            'sample_text' => $e->sample_text,
            'sample_params' => is_array($e->sample_params) ? $e->sample_params : [],
            'intro' => (bool) $e->intro,
        ];

        $audio = $this->store->audioPath((string) $e->slug);
        if ($audio !== null && is_file($audio)) {
            $out['audio'] = [
                'ext' => pathinfo($audio, PATHINFO_EXTENSION) ?: 'mp3',
                'data' => base64_encode((string) file_get_contents($audio)),
            ];
        }

        return $out;
    }

    /**
     * Recreate the effects from an export payload into the active project. Each
     * gets a fresh id and a collision-free slug, is promoted (component written,
     * bundle rebuilt) and has its preview queued. Returns how many were imported.
     *
     * @param  array<string,mixed>  $payload
     */
    public function import(array $payload): int
    {
        if (($payload['type'] ?? null) !== self::TYPE || ! is_array($payload['effects'] ?? null)) {
            throw new RuntimeException('This is not a Brand Machine SFX export file.');
        }

        $count = 0;
        foreach ($payload['effects'] as $entry) {
            if (! is_array($entry) || blank($entry['tsx'] ?? null) || blank($entry['slug'] ?? null)) {
                continue;
            }

            $slug = $this->uniqueSlug(Str::slug((string) $entry['slug']));
            $params = is_array($entry['sample_params'] ?? null) ? $entry['sample_params'] : [];

            $record = $this->store->create([
                'slug' => $slug,
                'display_name' => (string) ($entry['display_name'] ?? $slug),
                'description' => (string) ($entry['description'] ?? ''),
                'prompt' => (string) ($entry['prompt'] ?? ''),
                'tsx' => (string) $entry['tsx'],
                'sample_text' => $entry['sample_text'] ?? null,
                'sample_params' => $params,
                'intro' => (bool) ($entry['intro'] ?? false),
                'enabled' => true,
                'status' => EffectRecord::STATUS_ACTIVE,
            ]);

            // Write <slug>.tsx, mark active, rebuild the effects bundle.
            $this->library->promote($record);

            if (! blank($entry['audio']['data'] ?? null)) {
                $ext = preg_replace('/[^a-z0-9]/i', '', (string) ($entry['audio']['ext'] ?? 'mp3')) ?: 'mp3';
                $tmp = tempnam(sys_get_temp_dir(), 'sfx_audio_');
                file_put_contents($tmp, base64_decode((string) $entry['audio']['data']));
                $this->store->putAudio($slug, $tmp, $ext);
                @unlink($tmp);
            }

            // Render the preview like a freshly generated effect.
            RenderEffectSampleJob::dispatch($slug, $record->sample_text, $params);
            $count++;
        }

        return $count;
    }

    /** A slug that clashes with neither a built-in nor an existing custom effect. */
    private function uniqueSlug(string $desired): string
    {
        $desired = $desired !== '' ? $desired : 'effect';
        $slug = $desired;
        $n = 1;
        while ($this->library->isBuiltin($slug) || $this->store->slugExists($slug)) {
            $slug = $desired.'-'.(++$n);
        }

        return $slug;
    }
}
