<?php

namespace App\Services\Themes;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Str;

/**
 * The themes are folders under the project's vault (themes/<slug>/), each with a
 * theme.json naming it and the theme's own design system, animation style guide,
 * SFX (+refs, audio) and backgrounds. No registry: a folder is a theme.
 */
class ThemeRepository
{
    public function root(): string
    {
        // Resolved per call: the project (and so the vault) can switch mid-process.
        return rtrim(app(ProjectContext::class)->vaultPath(), '/').'/themes';
    }

    /** @return list<Theme> — the default first, then by name. */
    public function all(): array
    {
        $temas = array_map(fn (string $dir) => $this->fromDir($dir), glob($this->root().'/*', GLOB_ONLYDIR) ?: []);
        $padrao = $this->defaultSlug();
        if (! in_array($padrao, array_map(fn (Theme $t) => $t->slug, $temas), true)) {
            $temas[] = $this->fromDir($this->root().'/'.$padrao);   // not created yet: still the default
        }
        usort($temas, fn (Theme $a, Theme $b) => [$a->slug !== $padrao, $a->name] <=> [$b->slug !== $padrao, $b->name]);

        return $temas;
    }

    public function find(string $slug): ?Theme
    {
        foreach ($this->all() as $tema) {
            if ($tema->slug === $slug) {
                return $tema;
            }
        }

        return null;
    }

    public function default(): Theme
    {
        return $this->find($this->defaultSlug()) ?? $this->all()[0];
    }

    /**
     * The theme for a listing portal: '<portal>-premium' (iad-premium, remax-premium,
     * idealista-premium) when that theme exists, else the default.
     */
    public function forPortal(?string $portal): Theme
    {
        return ($portal ? $this->find($portal.'-premium') : null) ?? $this->default();
    }

    /** A new, empty theme: the Design System page and the Effects Studio fill it. */
    public function create(string $name): Theme
    {
        $base = Str::slug($name) ?: 'tema';
        $slug = $base;
        for ($i = 2; is_dir($this->root().'/'.$slug); $i++) {
            $slug = "{$base}-{$i}";
        }
        $dir = $this->root().'/'.$slug;
        @mkdir($dir, 0775, true);
        file_put_contents($dir.'/theme.json', json_encode(['name' => trim($name)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return $this->fromDir($dir);
    }

    private function defaultSlug(): string
    {
        return (string) config('contentmachine.themes.default', 'iad-premium');
    }

    private function fromDir(string $dir): Theme
    {
        $slug = basename($dir);
        $meta = is_file($dir.'/theme.json') ? (array) json_decode((string) file_get_contents($dir.'/theme.json'), true) : [];

        return new Theme($slug, (string) ($meta['name'] ?? Str::headline($slug)), $dir);
    }
}
