<?php

namespace App\Services\Clips\Store;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vault-backed store for custom clip BACKGROUNDS — the full-frame backdrop a clip
 * renders behind its scenes. Each background is one JSON file in the ACTIVE
 * project's vault (backgrounds/<id>.json); so backgrounds are per-project and
 * there is no database. Mirrors EffectStore (SFX), but a background is a whole-clip
 * backdrop, NOT a scene layer, so it stays out of the planner's layer vocabulary.
 *
 * Two kinds: `code` (a generated Remotion component, like an SFX) and `video`
 * (an uploaded mp4, looped to fill any clip length). Both live in the same library
 * and share the enabled/disabled toggle.
 */
class BackgroundStore
{
    public const KIND_CODE = 'code';

    public const KIND_VIDEO = 'video';

    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/backgrounds';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
    }

    /** Absolute path of a video background's uploaded file. */
    public function videoPath(string $id): string
    {
        return $this->dir().'/'.$id.'.mp4';
    }

    /** @return Collection<int,EffectRecord> */
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

    public function findBySlug(string $slug): ?EffectRecord
    {
        return $this->all()->first(fn (EffectRecord $r) => $r->slug === $slug);
    }

    /** @return Collection<int,EffectRecord> status = active */
    public function active(): Collection
    {
        return $this->all()->filter(fn (EffectRecord $r) => $r->status === EffectRecord::STATUS_ACTIVE)->values();
    }

    /** @return Collection<int,EffectRecord> active AND allowed (enabled) — the ones the planner may pick */
    public function enabled(): Collection
    {
        return $this->active()->filter(fn (EffectRecord $r) => (bool) $r->get('enabled', true))->values();
    }

    public function slugExists(string $slug): bool
    {
        return $this->all()->contains(fn (EffectRecord $r) => $r->slug === $slug);
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): EffectRecord
    {
        $now = now()->toIso8601String();
        $record = new EffectRecord($this, array_merge([
            'status' => EffectRecord::STATUS_PENDING,
            'enabled' => true,
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

    public function deleteById(string $id): void
    {
        @unlink($this->path($id));
        @unlink($this->videoPath($id));
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
            $id = 'bg-'.Str::lower(Str::random(8));
        } while (is_file($this->path($id)));

        return $id;
    }
}
