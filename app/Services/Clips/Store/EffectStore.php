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

    // ── intro built-ins (per project): built-ins the planner may open with ─

    private function introPath(): string
    {
        return $this->dir().'/_intro-builtins.json';
    }

    /** @return string[] */
    public function introBuiltins(): array
    {
        $file = $this->introPath();
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_values(array_filter($data, 'is_string')) : [];
    }

    /** @param string[] $slugs */
    public function setIntroBuiltins(array $slugs): void
    {
        @mkdir($this->dir(), 0775, true);
        file_put_contents($this->introPath(), json_encode(array_values(array_unique($slugs)), JSON_PRETTY_PRINT));
    }

    // ── per-effect audio (SFX sound) ─────────────────────────────────────
    // A sound attached to an effect lives at sfx-audio/<slug>.<ext>; the
    // filename IS the slug, so custom effects and built-ins are keyed alike.
    // Slugs are kebab-case ([a-z0-9-]) so they carry no glob metacharacters.

    public function audioDir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/sfx-audio';
    }

    /** Absolute path of the sound attached to $slug, or null. */
    public function audioPath(string $slug): ?string
    {
        return (glob($this->audioDir().'/'.$slug.'.*') ?: [])[0] ?? null;
    }

    /** @return string[] slugs that have a sound attached */
    public function audioSlugs(): array
    {
        return collect(glob($this->audioDir().'/*.*') ?: [])
            ->map(fn (string $f) => pathinfo($f, PATHINFO_FILENAME))
            ->all();
    }

    /** Store $sourcePath as the sound for $slug, replacing any existing. */
    public function putAudio(string $slug, string $sourcePath, string $ext): void
    {
        $this->deleteAudio($slug);
        @mkdir($this->audioDir(), 0775, true);
        copy($sourcePath, $this->audioDir().'/'.$slug.'.'.strtolower($ext ?: 'mp3'));
    }

    public function deleteAudio(string $slug): void
    {
        foreach (glob($this->audioDir().'/'.$slug.'.*') ?: [] as $f) {
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
            $id = 'fx-'.Str::lower(Str::random(8));
        } while (is_file($this->path($id)));

        return $id;
    }
}
