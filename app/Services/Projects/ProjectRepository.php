<?php

namespace App\Services\Projects;

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

    private function makeDefault(): Project
    {
        return new Project(
            slug: 'default',
            name: (string) config('contentmachine.projects.default_name', 'IATECA'),
            path: (string) config('contentmachine.projects.default_vault', base_path('vault')),
            language: (string) config('app.locale', 'en'),
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
