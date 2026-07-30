<?php

namespace App\Providers;

use App\Services\Settings\SettingsOverlay;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\ServiceProvider;

/**
 * Applies the vault-stored settings overlay at boot for the default project.
 * The SetActiveProject middleware re-applies it per request once the active
 * project is known, so each project's own keys/models take effect. The overlay
 * logic itself lives in SettingsOverlay (shared by both).
 */
class SettingsOverlayProvider extends ServiceProvider
{
    public function boot(SettingsOverlay $overlay, SettingsRepository $settings): void
    {
        $overlay->apply($settings);
    }
}
