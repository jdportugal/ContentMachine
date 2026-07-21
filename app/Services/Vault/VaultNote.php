<?php

namespace App\Services\Vault;

use Illuminate\Contracts\Support\Arrayable;
use League\CommonMark\GithubFlavoredMarkdownConverter;

/**
 * Representa uma nota do vault (um ficheiro .md com frontmatter YAML).
 * Imutável do ponto de vista de consumo; usada como DTO pelas páginas.
 */
class VaultNote implements Arrayable
{
    public function __construct(
        public readonly string $path,        // caminho relativo à raiz do vault, ex.: "rascunhos/post-abc.md"
        public array $frontmatter,           // propriedades YAML
        public string $body,                 // corpo em Markdown
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

    /** Renderiza o corpo Markdown para HTML. */
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
