<?php

namespace App\Services\Settings;

/**
 * The API keys — the ONLY settings shared across every project. Stored as a
 * single JSON file outside any vault (so switching projects keeps your keys),
 * whereas all other settings live per-project in each vault's definicoes.md.
 *
 * A provider can hold SEVERAL keys (e.g. two OpenAI accounts). Each entry has a
 * stable id `<provider>:<n>` that the per-step bindings (`passos`) point at, so a
 * pipeline step can be pinned to one specific key. The FIRST entry of a provider
 * is its default — that is what config('services.<provider>.key') gets.
 *
 * On disk (v2):
 *   {"openai": [{"id":"openai:1","label":"Personal","value":"sk-…"}], …}
 * The v1 flat shape ({"openai":"sk-…"}) is still read and upgraded in memory.
 */
class SharedKeys
{
    private function path(): string
    {
        return (string) config('contentmachine.settings.keys_path', storage_path('app/settings-keys.json'));
    }

    /**
     * Every key, by provider, in order (the first is the default).
     *
     * @return array<string,array<int,array{id:string,label:string,value:string}>>
     */
    public function entries(): array
    {
        $file = $this->path();
        if (! is_file($file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $provider => $keys) {
            $provider = (string) $provider;

            // v1: "provider": "sk-…" — a single unnamed key.
            if (is_string($keys)) {
                $keys = trim($keys) === '' ? [] : [['value' => $keys]];
            }
            if (! is_array($keys)) {
                continue;
            }

            $lista = [];
            foreach (array_values($keys) as $i => $entry) {
                $value = is_array($entry) ? (string) ($entry['value'] ?? '') : (string) $entry;
                if (trim($value) === '') {
                    continue;
                }
                $lista[] = [
                    'id' => (string) (is_array($entry) ? ($entry['id'] ?? '') : '') ?: $provider.':'.($i + 1),
                    'label' => trim((string) (is_array($entry) ? ($entry['label'] ?? '') : '')),
                    'value' => trim($value),
                ];
            }

            if ($lista !== []) {
                $out[$provider] = $lista;
            }
        }

        return $out;
    }

    /**
     * The DEFAULT key of each provider — the shape the config overlay consumes.
     *
     * @return array<string,string>
     */
    public function all(): array
    {
        return array_map(fn (array $lista) => $lista[0]['value'], $this->entries());
    }

    /** The value behind a key id, or null if it no longer exists. */
    public function value(string $id): ?string
    {
        foreach ($this->entries() as $lista) {
            foreach ($lista as $entry) {
                if ($entry['id'] === $id) {
                    return $entry['value'];
                }
            }
        }

        return null;
    }

    /** Adds a key to a provider. Returns its new id. */
    public function add(string $provider, string $value, string $label = ''): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $todas = $this->entries();
        $id = $this->novoId($provider, $todas);
        $todas[$provider][] = ['id' => $id, 'label' => trim($label), 'value' => $value];
        $this->write($todas);

        return $id;
    }

    /** Renames a key (the label is only a human hint — the id never changes). */
    public function rename(string $id, string $label): void
    {
        $todas = $this->entries();
        foreach ($todas as $provider => $lista) {
            foreach ($lista as $i => $entry) {
                if ($entry['id'] === $id) {
                    $todas[$provider][$i]['label'] = trim($label);
                    $this->write($todas);

                    return;
                }
            }
        }
    }

    /** Removes one key by id. */
    public function remove(string $id): void
    {
        $todas = $this->entries();
        foreach ($todas as $provider => $lista) {
            $restantes = array_values(array_filter($lista, fn (array $e) => $e['id'] !== $id));
            if (count($restantes) === count($lista)) {
                continue;
            }
            if ($restantes === []) {
                unset($todas[$provider]);
            } else {
                $todas[$provider] = $restantes;
            }
            $this->write($todas);

            return;
        }
    }

    /**
     * Flat save (provider => value), the shape the settings form has always used:
     * a value REPLACES the provider's default key (adding it if there is none),
     * and an empty string removes every key of that provider.
     *
     * @param  array<string,mixed>  $keys
     */
    public function save(array $keys): void
    {
        $todas = $this->entries();

        foreach ($keys as $provider => $value) {
            $provider = (string) $provider;
            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                unset($todas[$provider]);

                continue;
            }

            if (isset($todas[$provider][0])) {
                $todas[$provider][0]['value'] = $value;
            } else {
                $todas[$provider] = [['id' => $this->novoId($provider, $todas), 'label' => '', 'value' => $value]];
            }
        }

        $this->write($todas);
    }

    /** First `<provider>:<n>` not already taken (ids are never reused). */
    private function novoId(string $provider, array $todas): string
    {
        $usados = array_column($todas[$provider] ?? [], 'id');
        for ($n = 1;; $n++) {
            $id = $provider.':'.$n;
            if (! in_array($id, $usados, true)) {
                return $id;
            }
        }
    }

    /** @param array<string,array<int,array{id:string,label:string,value:string}>> $todas */
    private function write(array $todas): void
    {
        $file = $this->path();
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode($todas, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
