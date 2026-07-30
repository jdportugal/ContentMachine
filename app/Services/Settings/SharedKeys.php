<?php

namespace App\Services\Settings;

/**
 * The API keys — the ONLY settings shared across every project. Stored as a
 * single JSON file outside any vault (so switching projects keeps your keys),
 * whereas all other settings live per-project in each vault's definicoes.md.
 */
class SharedKeys
{
    private function path(): string
    {
        return (string) config('contentmachine.settings.keys_path', storage_path('app/settings-keys.json'));
    }

    /** @return array<string,string> */
    public function all(): array
    {
        $file = $this->path();
        if (! is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return is_array($data) ? $data : [];
    }

    /** Merge onto the stored keys (partial saves are fine). @param array<string,mixed> $keys */
    public function save(array $keys): void
    {
        $merged = array_merge($this->all(), array_map(fn ($v) => is_string($v) ? trim($v) : $v, $keys));
        $file = $this->path();
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
