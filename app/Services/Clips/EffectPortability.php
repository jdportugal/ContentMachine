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
     * Build the export payload. $id is an effect id, a built-in slug, or 'all'
     * (every active custom effect plus any built-in carrying a customization —
     * a sound or the intro flag). Returns null when there is nothing to export.
     *
     * Custom effects carry their full component source. Built-ins carry only a
     * reference (slug + sample + sound + intro): the component itself ships with
     * every install, so on import it is re-applied to the matching built-in.
     *
     * @return array<string,mixed>|null
     */
    public function export(string $id): ?array
    {
        $entries = [];

        if ($id === 'all') {
            foreach ($this->store->active()->values() as $e) {
                $entries[] = $this->exportOne($e);
            }
            foreach (array_keys(EffectLibrary::BUILTIN_SAMPLES) as $slug) {
                if ($this->builtinHasCustomization($slug)) {
                    $entries[] = $this->exportBuiltin($slug);
                }
            }
        } elseif ($this->library->isBuiltin($id)) {
            $entries[] = $this->exportBuiltin($id);
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
            'effects' => $entries,
        ];
    }

    /** A built-in worth exporting inside "all" — it has a sound or is an intro. */
    private function builtinHasCustomization(string $slug): bool
    {
        return $this->store->audioPath($slug) !== null || $this->library->builtinIsIntro($slug);
    }

    /** @return array<string,mixed> a self-contained reference to a built-in effect */
    private function exportBuiltin(string $slug): array
    {
        $meta = EffectLibrary::BUILTIN_SAMPLES[$slug] ?? [];
        $out = [
            'builtin' => true,
            'slug' => $slug,
            'display_name' => (string) ($meta['label'] ?? $slug),
            'sample_text' => $meta['text'] ?? '',
            'sample_params' => $meta['params'] ?? [],
            'intro' => $this->library->builtinIsIntro($slug),
        ];

        $this->attachAudio($out, $slug);

        return $out;
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

        $this->attachAudio($out, (string) $e->slug);

        return $out;
    }

    /** Embed the effect's attached sound (base64) into an export entry, if any. */
    private function attachAudio(array &$out, string $slug): void
    {
        $audio = $this->store->audioPath($slug);
        if ($audio !== null && is_file($audio)) {
            $out['audio'] = [
                'ext' => pathinfo($audio, PATHINFO_EXTENSION) ?: 'mp3',
                'data' => base64_encode((string) file_get_contents($audio)),
            ];
        }
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
            if (! is_array($entry) || blank($entry['slug'] ?? null)) {
                continue;
            }

            // A built-in reference: re-apply its sound + intro to the matching
            // built-in on this install (the component itself already ships here).
            if (! empty($entry['builtin'])) {
                $count += $this->applyBuiltin($entry) ? 1 : 0;

                continue;
            }

            if (blank($entry['tsx'] ?? null)) {
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
                $this->writeAudio($slug, $entry['audio']);
            }

            // Render the preview like a freshly generated effect.
            RenderEffectSampleJob::dispatch($slug, $record->sample_text, $params);
            $count++;
        }

        return $count;
    }

    /**
     * Re-apply a built-in reference to this install: attach its sound and turn on
     * intro. Non-destructive — never disables or un-intros. Returns whether it
     * applied anything (false when the built-in is unknown here or is pristine).
     *
     * @param  array<string,mixed>  $entry
     */
    private function applyBuiltin(array $entry): bool
    {
        $slug = (string) $entry['slug'];
        if (! $this->library->isBuiltin($slug)) {
            return false; // a different version — can't recreate without its source
        }

        $applied = false;

        if (! blank($entry['audio']['data'] ?? null)) {
            $this->writeAudio($slug, $entry['audio']);
            $applied = true;
        }

        if (! empty($entry['intro']) && ! $this->library->builtinIsIntro($slug)) {
            $this->library->toggleIntroBuiltin($slug);
            $applied = true;
        }

        return $applied;
    }

    /** Decode a base64 audio entry and store it as $slug's sound. */
    private function writeAudio(string $slug, array $audio): void
    {
        $ext = preg_replace('/[^a-z0-9]/i', '', (string) ($audio['ext'] ?? 'mp3')) ?: 'mp3';
        $tmp = tempnam(sys_get_temp_dir(), 'sfx_audio_');
        file_put_contents($tmp, base64_decode((string) $audio['data']));
        $this->store->putAudio($slug, $tmp, $ext);
        @unlink($tmp);
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
