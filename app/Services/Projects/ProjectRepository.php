<?php

namespace App\Services\Projects;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The project registry — a JSON file listing every workspace (no database).
 * Seeds a default project from the legacy vault on first use, so the existing
 * content becomes the first project with zero data movement.
 */
class ProjectRepository
{
    /** Vault subfolders seeded for a brand-new project. */
    private const FOLDERS = ['noticias', 'clips', 'clips/fontes', 'clips-animados', 'rascunhos', 'publicacoes', 'monitorizacao', 'definicoes'];

    /**
     * What a clone takes: the project's SETUP — brand, effects, libraries,
     * settings. Not its content (clips, news, drafts, publications): a clone is
     * a new workspace built the same way, and clip records point at shared upload
     * files that two projects must not both own.
     */
    private const CLONE_ITEMS = [
        'design-system.md', 'design-system.tokens.json', 'estilo-animacao.md', 'definicoes',
        'sfx', 'sfx-audio', 'sfx-refs', 'backgrounds', 'vfx', 'image-library', 'avatars',
    ];

    private function registryPath(): string
    {
        return (string) config('contentmachine.projects.registry', storage_path('app/projects.json'));
    }

    /** @return list<Project> */
    public function all(): array
    {
        $rows = $this->load();
        if ($rows === []) {
            $this->save([$this->makeDefault()->toArray()]);
            $rows = $this->load();
        }

        return array_map(fn ($r) => Project::fromArray($r), $rows);
    }

    public function find(string $slug): ?Project
    {
        foreach ($this->all() as $p) {
            if ($p->slug === $slug) {
                return $p;
            }
        }

        return null;
    }

    /** First registered project — the fallback when nothing is selected. */
    public function default(): Project
    {
        return $this->all()[0];
    }

    public function exists(string $slug): bool
    {
        return $this->find($slug) !== null;
    }

    /** Update a project's editable fields (name, language) in the registry. */
    public function update(string $slug, array $attrs): Project
    {
        $this->all(); // ensure the default is seeded
        $rows = $this->load();
        foreach ($rows as &$row) {
            if (($row['slug'] ?? null) === $slug) {
                if (isset($attrs['name'])) {
                    $row['name'] = trim((string) $attrs['name']) ?: $row['name'];
                }
                if (isset($attrs['language'])) {
                    $row['language'] = (string) $attrs['language'] ?: $row['language'];
                }
                $this->save($rows);

                return Project::fromArray($row);
            }
        }
        unset($row);

        throw new \RuntimeException("Project [{$slug}] not found.");
    }

    /** Create a new project: a fresh vault directory + a registry entry. */
    public function create(string $name, string $language = 'en'): Project
    {
        $name = trim($name) !== '' ? trim($name) : 'Project';
        $slug = $this->uniqueSlug($name);
        $path = rtrim((string) config('contentmachine.projects.root'), '/').'/'.$slug;

        foreach (self::FOLDERS as $folder) {
            @mkdir($path.'/'.$folder, 0775, true);
        }

        $project = new Project($slug, $name, $path, $language ?: 'en');
        $rows = $this->load();
        $rows[] = $project->toArray();
        $this->save($rows);

        return $project;
    }

    /**
     * A new project that starts as a copy of $slug's setup (see CLONE_ITEMS).
     *
     * Everything is COPIED into the clone's own vault, so from here on the two are
     * independent: the clone's effects are its own records, and since effects
     * paint with the design-system tokens, changing the clone's design system
     * re-themes the clone's effects only.
     */
    public function duplicate(string $slug, string $name): Project
    {
        $source = $this->find($slug) ?? throw new \RuntimeException("Project [{$slug}] not found.");
        $clone = $this->create($name, $source->language);
        $from = rtrim($source->path, '/');
        $to = rtrim($clone->path, '/');

        foreach (self::CLONE_ITEMS as $item) {
            if (is_dir($from.'/'.$item)) {
                File::copyDirectory($from.'/'.$item, $to.'/'.$item);
            } elseif (is_file($from.'/'.$item)) {
                copy($from.'/'.$item, $to.'/'.$item);
            }
        }

        // Records that name a file inside the source vault (image-library
        // sidecars…) must name the clone's copy. A cached preview path points into
        // the shared preview folder — dropped, or deleting the clone's effect would
        // delete the original's preview with it; the clone renders its own.
        foreach (File::allFiles($to) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $data = json_decode((string) file_get_contents($file->getPathname()), true);
            if (! is_array($data)) {
                continue;
            }
            unset($data['preview_path']);
            array_walk_recursive($data, function (&$v) use ($from, $to) {
                if (is_string($v) && str_starts_with($v, $from.'/')) {
                    $v = $to.substr($v, strlen($from));
                }
            });
            file_put_contents($file->getPathname(), json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        return $clone;
    }

    private function makeDefault(): Project
    {
        return new Project(
            slug: 'default',
            name: (string) config('contentmachine.projects.default_name', 'Brand Machine'),
            path: (string) config('contentmachine.projects.default_vault', base_path('vault')),
            language: (string) config('contentmachine.projects.default_language', config('app.locale', 'en')),
        );
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'project';
        $slug = $base;
        $i = 2;
        $taken = array_column($this->load(), 'slug');
        while (in_array($slug, $taken, true) || $slug === 'default') {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** @return list<array<string,mixed>> */
    private function load(): array
    {
        $file = $this->registryPath();
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? array_values($data) : [];
    }

    /** @param list<array<string,mixed>> $rows */
    private function save(array $rows): void
    {
        $file = $this->registryPath();
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
