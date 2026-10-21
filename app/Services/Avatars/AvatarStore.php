<?php

namespace App\Services\Avatars;

use App\Services\Clips\Store\EffectRecord;
use App\Services\Projects\ProjectContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vault-backed store for Avatar Gen renders — one JSON file per lip-sync in the
 * ACTIVE project's vault (avatars/<id>.json) with the finished video beside it
 * (avatars/<id>.mp4). Same shape and same record class as the VFX Lab, so
 * avatars are per-project and there is no database table.
 */
class AvatarStore
{
    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/avatars';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
    }

    public function videoPath(string $id): string
    {
        return $this->dir().'/'.$id.'.mp4';
    }

    /** The finished video for a record, or null while it is pending/failed. */
    public function videoFor(EffectRecord $avatar): ?string
    {
        $file = $this->videoPath($avatar->id());

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

    /** Delete the record AND its video — ids are [a-z0-9-], so no glob metacharacters. */
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
            $id = 'avatar-'.Str::lower(Str::random(8));
        } while (is_file($this->path($id)));

        return $id;
    }
}
