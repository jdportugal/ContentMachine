<?php

namespace App\Services\Clips\Store;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vault-backed store for custom SFX. Each effect is one JSON file in the ACTIVE
 * project's vault (sfx/<id>.json); disallowed built-ins are a single JSON list
 * (sfx/_disabled-builtins.json). So SFX are per-project automatically and there
 * is no database. Replaces ClipEffect + DisabledBuiltinEffect.
 */
class EffectStore
{
    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/sfx';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
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

    /** @return Collection<int,EffectRecord> status = active */
    public function active(): Collection
    {
        return $this->all()->filter(fn (EffectRecord $r) => $r->status === EffectRecord::STATUS_ACTIVE)->values();
    }

    /** @return Collection<int,EffectRecord> active AND allowed (enabled) */
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
    }

    // ── disabled built-ins (per project) ─────────────────────────────────

    private function disabledPath(): string
    {
        return $this->dir().'/_disabled-builtins.json';
    }

    /** @return string[] */
    public function disabledBuiltins(): array
    {
        $file = $this->disabledPath();
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_values(array_filter($data, 'is_string')) : [];
    }

    /** @param string[] $slugs */
    public function setDisabledBuiltins(array $slugs): void
    {
        @mkdir($this->dir(), 0775, true);
        file_put_contents($this->disabledPath(), json_encode(array_values(array_unique($slugs)), JSON_PRETTY_PRINT));
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
            $id = 'fx-'.Str::lower(Str::random(8));
        } while (is_file($this->path($id)));

        return $id;
    }
}
