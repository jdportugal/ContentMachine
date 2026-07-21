<?php

namespace App\Services\Vault;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\YamlFrontMatter\YamlFrontMatter;
use Symfony\Component\Yaml\Yaml;

/**
 * Lê e escreve o vault Obsidian (pasta de ficheiros .md com frontmatter YAML).
 * É a ponte entre a aplicação e o "cérebro"/memória do sistema.
 */
class VaultRepository implements VaultContract
{
    public function __construct(private readonly string $root) {}

    public function root(): string
    {
        return $this->root;
    }

    public function all(string $folder = '', bool $recursive = true): Collection
    {
        $base = $this->absolute($folder);

        if (! is_dir($base)) {
            return collect();
        }

        $files = $recursive
            ? $this->globRecursive($base, '*.md')
            : (glob($base.'/*.md') ?: []);

        return collect($files)
            ->map(fn (string $abs) => $this->readAbsolute($abs))
            ->filter()
            ->sortByDesc(fn (VaultNote $n) => $n->get('data') ?? $n->get('atualizado_em') ?? '')
            ->values();
    }

    public function get(string $path): ?VaultNote
    {
        return $this->readAbsolute($this->absolute($path));
    }

    public function put(string $path, array $frontmatter, string $body): VaultNote
    {
        $path = $this->normalizeMdPath($path);
        $abs = $this->absolute($path);

        @mkdir(dirname($abs), 0775, true);

        file_put_contents($abs, $this->compose($frontmatter, $body));

        return new VaultNote($path, $frontmatter, $body);
    }

    public function create(string $folder, array $frontmatter, string $body = ''): VaultNote
    {
        $title = $frontmatter['titulo'] ?? $frontmatter['title'] ?? 'nota';
        $slug = Str::slug($title) ?: 'nota';
        $slug = $slug.'-'.Str::lower(Str::random(4));

        $frontmatter = array_merge([
            'data' => now()->toDateString(),
            'atualizado_em' => now()->toIso8601String(),
        ], $frontmatter);

        return $this->put(trim($folder, '/').'/'.$slug.'.md', $frontmatter, $body);
    }

    public function updateFrontmatter(string $path, array $patch): VaultNote
    {
        $note = $this->get($path);

        if (! $note) {
            throw new \RuntimeException("Nota não encontrada: {$path}");
        }

        $frontmatter = array_merge($note->frontmatter, $patch, [
            'atualizado_em' => now()->toIso8601String(),
        ]);

        return $this->put($note->path, $frontmatter, $note->body);
    }

    public function delete(string $path): bool
    {
        $abs = $this->absolute($this->normalizeMdPath($path));

        return is_file($abs) ? unlink($abs) : false;
    }

    // ---------------------------------------------------------------------

    private function readAbsolute(string $abs): ?VaultNote
    {
        if (! is_file($abs)) {
            return null;
        }

        $document = YamlFrontMatter::parse(file_get_contents($abs));

        return new VaultNote(
            path: $this->relative($abs),
            frontmatter: $document->matter(),
            body: trim($document->body()),
        );
    }

    private function compose(array $frontmatter, string $body): string
    {
        $yaml = trim(Yaml::dump($frontmatter, 4, 2));

        return "---\n{$yaml}\n---\n\n".trim($body)."\n";
    }

    private function absolute(string $path): string
    {
        $path = ltrim($this->sanitize($path), '/');

        return $path === '' ? $this->root : $this->root.'/'.$path;
    }

    private function relative(string $abs): string
    {
        return ltrim(Str::after($abs, $this->root), '/');
    }

    private function normalizeMdPath(string $path): string
    {
        $path = $this->sanitize($path);

        return str_ends_with($path, '.md') ? $path : $path.'.md';
    }

    /** Impede escape da raiz do vault (path traversal). */
    private function sanitize(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        $parts = array_filter(explode('/', $path), fn ($p) => $p !== '' && $p !== '.' && $p !== '..');

        return implode('/', $parts);
    }

    /** @return string[] */
    private function globRecursive(string $base, string $pattern): array
    {
        $results = glob($base.'/'.$pattern) ?: [];

        foreach (glob($base.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $results = array_merge($results, $this->globRecursive($dir, $pattern));
        }

        return $results;
    }
}
