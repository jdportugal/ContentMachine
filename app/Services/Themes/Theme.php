<?php

namespace App\Services\Themes;

/** A video theme: its folder holds a design system, style guide, SFX and backgrounds. */
final class Theme
{
    public function __construct(
        public readonly string $slug,
        public readonly string $name,
        public readonly string $path,
    ) {}
}
