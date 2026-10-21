<?php

namespace App\Services\Themes;

/**
 * The theme in use right now — the studio's chosen one on a web request
 * (SetActiveProject), the clip's own on a queued job (ActivateProject). Points
 * the design-system and style-guide config at it; EffectStore and BackgroundStore
 * read their folders from path(). Keeps only the slug, so it follows a project switch.
 *
 * ponytail: renders rebuild remotion/src/effects + backgrounds from the ACTIVE theme,
 * so two workers rendering different themes at once would clobber each other. One
 * worker on the clip queue (as deployed) is safe; per-theme Remotion dirs if that grows.
 */
class ThemeContext
{
    private ?string $slug = null;

    public function __construct(private ThemeRepository $themes) {}

    public function current(): Theme
    {
        return ($this->slug !== null ? $this->themes->find($this->slug) : null) ?? $this->themes->default();
    }

    public function path(): string
    {
        return $this->current()->path;
    }

    /** Switch to $slug (unknown or empty = the default theme). */
    public function use(?string $slug): void
    {
        $this->slug = $slug !== null && $slug !== '' && $this->themes->find($slug) ? $slug : null;
        $this->apply();
    }

    /** Re-point the design-system and style-guide paths at the current theme. */
    public function apply(): void
    {
        $path = $this->path();
        config([
            'contentmachine.design_system.path' => $path.'/design-system.md',
            'contentmachine.clips.style_md' => $path.'/estilo-animacao.md',
        ]);
    }
}
