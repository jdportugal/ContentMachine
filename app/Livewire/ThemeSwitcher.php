<?php

namespace App\Livewire;

use App\Services\Themes\ThemeContext;
use App\Services\Themes\ThemeRepository;
use Livewire\Component;

/**
 * The sidebar's theme picker: which theme the studio pages (Design System, Effects
 * Studio, Animated Clips) show and edit, and the default for new videos. Also
 * creates a theme — an empty one, filled from the Design System page and the studio.
 */
class ThemeSwitcher extends Component
{
    public string $novo = '';

    public function mudar(string $slug, ThemeRepository $themes)
    {
        if ($themes->find($slug)) {
            session(['theme' => $slug]);
        }

        return $this->redirect(request()->header('Referer') ?: route('imoveis'));
    }

    public function criar(ThemeRepository $themes)
    {
        $this->validate(['novo' => 'required|string|max:60'], ['novo.required' => 'Dê um nome ao tema.']);
        session(['theme' => $themes->create($this->novo)->slug]);

        return $this->redirect(route('design-system'));
    }

    public function render(ThemeRepository $themes, ThemeContext $context)
    {
        return view('livewire.theme-switcher', ['temas' => $themes->all(), 'atual' => $context->current()]);
    }
}
