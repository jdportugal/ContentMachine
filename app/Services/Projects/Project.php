<?php

namespace App\Services\Projects;

/**
 * A workspace: a named vault directory with its own settings, design system and
 * language. Immutable value object — the registry (ProjectRepository) owns
 * persistence.
 */
class Project
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $path,      // absolute vault directory
        public readonly string $language,  // locale, e.g. 'en' | 'pt'
    ) {}

    /** @return array<string,string> */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'path' => $this->path,
            'language' => $this->language,
        ];
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            slug: (string) ($row['slug'] ?? ''),
            name: (string) ($row['name'] ?? ($row['slug'] ?? 'Project')),
            path: (string) ($row['path'] ?? ''),
            language: (string) ($row['language'] ?? 'en'),
        );
    }
}
