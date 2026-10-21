<?php

namespace App\Services\Clips\Store;

use App\Services\Projects\ProjectContext;
use App\Services\Themes\ThemeContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Vault-backed store for custom SFX. Each effect is one JSON file in the ACTIVE
 * THEME's folder (themes/<slug>/sfx/<id>.json); disallowed built-ins are a single
 * JSON list (sfx/_disabled-builtins.json). So SFX are per-theme automatically and
 * there is no database. The after-intro line stays on the project.
 */
class EffectStore
{
    public function __construct(private ProjectContext $context, private ThemeContext $theme) {}

    public function dir(): string
    {
        return rtrim($this->theme->path(), '/').'/sfx';
    }

    public function path(string $id): string
    {
        return $this->dir().'/'.$id.'.json';
    }

    /**
     * Every record on disk, INCLUDING the clip-scoped ones written by Free SFX.
     * Only the registry (active()) and uniqueness checks want those — the SFX page
     * and everything else wants all(), which leaves them out.
     *
     * @return Collection<int,EffectRecord>
     */
    public function records(): Collection
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

    /**
     * The SFX LIBRARY: the effects a human made and manages. A Free-SFX component
     * belongs to one clip, not to the library, so it never shows up here — but it
     * still has to reach the renderer, which is why active() reads records().
     *
     * @return Collection<int,EffectRecord>
     */
    public function all(): Collection
    {
        return $this->records()->reject(fn (EffectRecord $r) => (string) $r->get('clip_id', '') !== '')->values();
    }

    /** Free-SFX components belonging to ONE clip, in the order they were made. @return Collection<int,EffectRecord> */
    public function forClip(string $clipId): Collection
    {
        return $this->records()
            ->filter(fn (EffectRecord $r) => (string) $r->get('clip_id', '') === $clipId)
            ->sortBy(fn (EffectRecord $r) => (string) $r->get('created_at', ''))
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
        return $this->records()->filter(fn (EffectRecord $r) => $r->status === EffectRecord::STATUS_ACTIVE)->values();
    }

    /** @return Collection<int,EffectRecord> active AND allowed (enabled) */
    public function enabled(): Collection
    {
        return $this->active()->filter(fn (EffectRecord $r) => (bool) $r->get('enabled', true))->values();
    }

    public function slugExists(string $slug): bool
    {
        return $this->records()->contains(fn (EffectRecord $r) => $r->slug === $slug);
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

    private function outroPath(): string
    {
        return $this->dir().'/_outro-builtins.json';
    }

    /** Built-ins marked as outro (the planner may close the video with them). @return string[] */
    public function outroBuiltins(): array
    {
        $data = is_file($this->outroPath()) ? json_decode((string) file_get_contents($this->outroPath()), true) : [];

        return is_array($data) ? array_values(array_filter($data, 'is_string')) : [];
    }

    /** @param string[] $slugs */
    public function setOutroBuiltins(array $slugs): void
    {
        @mkdir($this->dir(), 0775, true);
        file_put_contents($this->outroPath(), json_encode(array_values(array_unique($slugs)), JSON_PRETTY_PRINT));
    }

    // ── per-effect audio (SFX sound) ─────────────────────────────────────
    // A sound attached to an effect lives at sfx-audio/<slug>.<ext>; the
    // filename IS the slug, so custom effects and built-ins are keyed alike.
    // Slugs are kebab-case ([a-z0-9-]) so they carry no glob metacharacters.

    public function audioDir(): string
    {
        return rtrim($this->theme->path(), '/').'/sfx-audio';
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
    /**
     * The project's "after intro" video (clips disk path), played full-screen right
     * after the intro on every clip. On the clips disk, not the vault: the vault is
     * committed and this is a video.
     */
    public function afterIntroPath(): ?string
    {
        $disk = Storage::disk(config('contentmachine.clips.disk'));

        return collect($disk->files('clips/after-intro'))
            ->first(fn ($f) => pathinfo($f, PATHINFO_FILENAME) === $this->context->current()->slug);
    }

    /** What the voiceover says over the after-intro video, right after the intro ('' = nothing added). */
    public function afterIntroLine(): string
    {
        $file = rtrim($this->context->vaultPath(), '/').'/after-intro.txt';

        return is_file($file) ? trim((string) file_get_contents($file)) : '';
    }

    /** A clip's after-intro line: the one typed with its listing, else the project's. */
    public function afterIntroLineFor(array $meta): string
    {
        return trim((string) ($meta['after_intro_line'] ?? '')) ?: $this->afterIntroLine();
    }

    public function putAfterIntroLine(string $line): void
    {
        file_put_contents(rtrim($this->context->vaultPath(), '/').'/after-intro.txt', trim($line));
    }

    public function putAfterIntro(string $sourcePath, string $ext): void
    {
        $this->deleteAfterIntro();
        Storage::disk(config('contentmachine.clips.disk'))
            ->put('clips/after-intro/'.$this->context->current()->slug.'.'.strtolower($ext ?: 'mp4'), fopen($sourcePath, 'r'));
    }

    public function deleteAfterIntro(): void
    {
        if ($path = $this->afterIntroPath()) {
            Storage::disk(config('contentmachine.clips.disk'))->delete($path);
        }
    }

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

    /** Absolute path of the mockup an effect was built from (sfx-refs/<id>.*), or null. */
    public function referencePath(string $id): ?string
    {
        return (glob(rtrim($this->theme->path(), '/').'/sfx-refs/'.$id.'.*') ?: [])[0] ?? null;
    }

    /** Store $sourcePath as the reference mockup for effect $id. */
    public function putReference(string $id, string $sourcePath, string $ext): void
    {
        $dir = rtrim($this->theme->path(), '/').'/sfx-refs';
        @mkdir($dir, 0775, true);
        copy($sourcePath, $dir.'/'.$id.'.'.strtolower($ext ?: 'png'));
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
