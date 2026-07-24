<?php

namespace App\Services\Clips\Store;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Vault-backed store for animated-clip projects. Each clip is one JSON file in
 * the ACTIVE project's vault (clips-animados/<id>.json), so clips are per-project
 * automatically and there is no database. Replaces the Eloquent ClipProject.
 */
class ClipStore
{
    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/clips-animados';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
    }

    /** Local render-output directory for a clip (audio, mp4). */
    public function storageDir(string $id): string
    {
        return storage_path('app/clips/'.$id);
    }

    /** @return Collection<int,ClipRecord> newest first */
    public function all(): Collection
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return collect();
        }

        return collect(glob($dir.'/*.json') ?: [])
            ->map(fn (string $file) => $this->hydrate($file))
            ->filter()
            ->sortByDesc(fn (ClipRecord $r) => (string) $r->get('created_at', ''))
            ->values();
    }

    public function find(string $id): ?ClipRecord
    {
        $file = $this->path($id);

        return is_file($file) ? $this->hydrate($file) : null;
    }

    public function findOrFail(string $id): ClipRecord
    {
        return $this->find($id) ?? throw new RuntimeException("Clip [{$id}] not found.");
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): ClipRecord
    {
        $id = $this->uniqueId((string) ($attrs['title'] ?? ''));
        $now = now()->toIso8601String();

        $record = new ClipRecord($this, array_merge([
            'status' => ClipRecord::STATUS_DRAFT,
        ], $attrs, [
            'id' => $id,
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->save($record);

        return $record;
    }

    public function save(ClipRecord $record): void
    {
        $record->attributes['updated_at'] = now()->toIso8601String();
        @mkdir($this->dir(), 0775, true);
        file_put_contents(
            $this->path($record->id),
            json_encode($record->attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    public function delete(string $id): void
    {
        @unlink($this->path($id));
        $dir = $this->storageDir($id);
        if (is_dir($dir)) {
            File::deleteDirectory($dir);
        }
    }

    private function hydrate(string $file): ?ClipRecord
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return null;
        }
        $data['id'] ??= pathinfo($file, PATHINFO_FILENAME);

        return new ClipRecord($this, $data);
    }

    private function uniqueId(string $title): string
    {
        $base = Str::slug(Str::limit(trim($title), 40, '')) ?: 'clip';
        do {
            $id = $base.'-'.Str::lower(Str::random(5));
        } while (is_file($this->path($id)));

        return $id;
    }
}
