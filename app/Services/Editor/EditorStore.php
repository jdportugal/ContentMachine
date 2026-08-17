<?php

namespace App\Services\Editor;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Vault-backed store for video edits — one JSON file per edit in the active
 * project's vault (editor/<id>.json), with the uploaded sources beside it.
 * Mirrors VfxStore: per-project by construction, no database.
 */
class EditorStore
{
    public const PENDING = 'pending';

    public const ANALYSING = 'analysing';

    public const REVIEW = 'review';

    public const RENDERING = 'rendering';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/editor';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
    }

    /** Where a source or rendered file for this edit lives. */
    public function filePath(string $id, string $role, string $ext = 'mp4'): string
    {
        return $this->dir().'/'.$id.'-'.$role.'.'.$ext;
    }

    /** @return Collection<int,EditRecord> newest first */
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
            ->sortByDesc(fn (EditRecord $r) => (string) $r->get('created_at', ''))
            ->values();
    }

    public function find(string $id): ?EditRecord
    {
        $file = $this->path($id);

        return is_file($file) ? $this->hydrate($file) : null;
    }

    /** @param array<string,mixed> $attrs */
    public function create(array $attrs): EditRecord
    {
        $now = now()->toIso8601String();
        $record = new EditRecord($this, array_merge([
            'status' => self::PENDING,
            'transcript' => [],
            'removals' => [],
        ], $attrs, [
            'id' => $this->uniqueId(),
            'created_at' => $now,
            'updated_at' => $now,
        ]));

        $this->save($record);

        return $record;
    }

    public function save(EditRecord $record): void
    {
        $record->attributes['updated_at'] = now()->toIso8601String();
        @mkdir($this->dir(), 0775, true);
        file_put_contents(
            $this->path($record->id()),
            json_encode($record->attributes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    /** Delete the record and every file belonging to it. Ids are [a-z0-9-]. */
    public function deleteById(string $id): void
    {
        @unlink($this->path($id));
        foreach (glob($this->dir().'/'.$id.'-*') ?: [] as $f) {
            @unlink($f);
        }
    }

    private function hydrate(string $file): ?EditRecord
    {
        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return null;
        }
        $data['id'] ??= pathinfo($file, PATHINFO_FILENAME);

        return new EditRecord($this, $data);
    }

    private function uniqueId(): string
    {
        do {
            $id = 'ed-'.Str::lower(Str::random(8));
        } while (is_file($this->path($id)));

        return $id;
    }
}
