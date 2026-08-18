<?php

namespace App\Services\Clips\Store;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vault-backed store for VFX Lab renders — one-off animations generated at a
 * chosen size/duration and downloaded as a finished video. Each is one JSON file
 * in the ACTIVE project's vault (vfx/<id>.json) with its video beside it
 * (vfx/<id>.mp4, or .mov for an alpha render), so VFX are per-project and there
 * is no database. Mirrors EffectStore, minus everything an SFX needs and a VFX
 * does not: no slug, no versions, no enable/intro toggles.
 *
 * The key difference from an SFX: a VFX is NEVER promoted into the render
 * registry (remotion/src/effects/index.ts). It is a finished asset for an
 * external editor, not a layer the planner can place in a clip.
 */
class VfxStore
{
    /** Aspect presets offered by the VFX Lab: key => [label, width, height]. */
    public const ASPECTS = [
        '16:9' => ['label' => '16:9 · landscape', 'width' => 1920, 'height' => 1080],
        '9:16' => ['label' => '9:16 · vertical', 'width' => 1080, 'height' => 1920],
        '1:1' => ['label' => '1:1 · square', 'width' => 1080, 'height' => 1080],
        '4:5' => ['label' => '4:5 · portrait', 'width' => 1080, 'height' => 1350],
    ];

    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/vfx';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
    }

    /** Absolute path of the rendered video (.mov for alpha, .mp4 otherwise). */
    public function videoPath(string $id, string $ext = 'mp4'): string
    {
        return $this->dir().'/'.$id.'.'.$ext;
    }

    /** The rendered video for a record, or null while it is pending/failed. */
    public function videoFor(EffectRecord $vfx): ?string
    {
        $file = $this->videoPath($vfx->id(), (string) $vfx->get('ext', 'mp4'));

        return is_file($file) ? $file : null;
    }

    /** @return Collection<int,EffectRecord> newest first */
    public function all(): Collection
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return collect();
        }

        return collect(glob($dir.'/*.json') ?: [])
            ->reject(fn (string $f) => str_starts_with(basename($f), '_'))
            ->map(fn (string $f) => $this->hydrate($f))
            ->filter()
            ->sortByDesc(fn (EffectRecord $r) => (string) $r->get('created_at', ''))
            ->values();
    }

    public function find(string $id): ?EffectRecord
    {
        $file = $this->path($id);

        return is_file($file) ? $this->hydrate($file) : null;
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): EffectRecord
    {
        $now = now()->toIso8601String();
        $record = new EffectRecord($this, array_merge([
            'status' => EffectRecord::STATUS_PENDING,
            'ext' => 'mp4',
        ], $attrs, [
            'id' => $this->uniqueId(),
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->save($record);

        return $record;
    }

    public function save(EffectRecord $record): void
    {
        $record->attributes['updated_at'] = now()->toIso8601String();
        @mkdir($this->dir(), 0775, true);
        file_put_contents(
            $this->path($record->id()),
            json_encode($record->attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /** Delete the record AND its video — ids are [a-z0-9-] so they carry no glob metacharacters. */
    public function deleteById(string $id): void
    {
        @unlink($this->path($id));
        foreach (glob($this->dir().'/'.$id.'.*') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function hydrate(string $file): ?EffectRecord
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return null;
        }
        $data['id'] ??= pathinfo($file, PATHINFO_FILENAME);

        return new EffectRecord($this, $data);
    }

    private function uniqueId(): string
    {
        do {
            $id = 'vfx-'.Str::lower(Str::random(8));
        } while (is_file($this->path($id)));

        return $id;
    }
}
