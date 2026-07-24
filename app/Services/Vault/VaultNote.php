<?php

namespace App\Services\Vault;

use Illuminate\Contracts\Support\Arrayable;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Represents a vault note (a .md file with YAML frontmatter).
 * Immutable from the consumption standpoint; used as a DTO by the pages.
 */
class VaultNote implements Arrayable
{
    public function __construct(
        public readonly string $path,        // path relative to the vault root, e.g. "rascunhos/post-abc.md"
        public array $frontmatter,           // YAML properties
        public string $body,                 // Markdown body
    ) {}

    public function slug(): string
    {
        return pathinfo($this->path, PATHINFO_FILENAME);
    }

    public function folder(): string
    {
        $dir = trim(pathinfo($this->path, PATHINFO_DIRNAME), '.');

        return $dir === '' ? '' : $dir;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->frontmatter[$key] ?? $default;
    }

    public function title(): string
    {
        return $this->frontmatter['titulo']
            ?? $this->frontmatter['title']
            ?? str($this->slug())->replace('-', ' ')->title();
    }

    /** Renders the Markdown body to HTML. */
    public function html(): string
    {
        return (new GithubFlavoredMarkdownConverter)->convert($this->body)->getContent();
    }

    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'slug' => $this->slug(),
            'frontmatter' => $this->frontmatter,
            'body' => $this->body,
        ];
    }
}
