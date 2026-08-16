<?php

namespace App\Services\Settings;

/**
 * Which API key a pipeline step must use.
 *
 * The user pins a step to one specific key in Settings → Steps; SettingsOverlay
 * resolves those bindings (key id → provider + secret) into config at boot and
 * on every project switch, so reading one here is a plain config lookup with no
 * vault I/O in the hot path.
 *
 * An unbound step returns null and the caller keeps its existing behaviour
 * (provider chain + the provider's default key).
 */
class StepKey
{
    /** @return array{provider:string,key:string}|null */
    public static function for(string $step): ?array
    {
        $bind = config('contentmachine.passos_resolvidos.'.$step);

        return is_array($bind) && isset($bind['provider']) ? $bind : null;
    }

    /**
     * The key to use for `$provider` in `$step`: the pinned one when the step is
     * bound to that very provider, otherwise the provider's default key.
     */
    public static function key(string $step, string $provider): string
    {
        $bind = self::for($step);

        return $bind !== null && $bind['provider'] === $provider
            ? $bind['key']
            : (string) config("services.{$provider}.key");
    }

    /** The provider pinned to a step ('' when it is on auto). */
    public static function provider(string $step): string
    {
        return self::for($step)['provider'] ?? '';
    }
}
