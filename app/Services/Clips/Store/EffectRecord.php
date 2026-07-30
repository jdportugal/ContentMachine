<?php

namespace App\Services\Clips\Store;

/**
 * A custom SFX, stored as a JSON file in the active project's vault
 * (sfx/<id>.json). Keyed by a stable id; `slug` is the layer type. Replaces the
 * old Eloquent ClipEffect — same fields and helpers.
 *
 * Also reused verbatim for custom BACKGROUNDS (BackgroundStore): identical
 * shape, different vault dir — hence the store union below.
 */
class EffectRecord
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UPDATING = 'updating';

    /** @param array<string,mixed> $attributes */
    public function __construct(private EffectStore|BackgroundStore $store, public array $attributes) {}

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function id(): string
    {
        return (string) ($this->attributes['id'] ?? '');
    }

    public function isActive(): bool
    {
        return ($this->attributes['status'] ?? null) === self::STATUS_ACTIVE;
    }

    /** @param array<string,mixed> $attrs */
    public function update(array $attrs): static
    {
        $this->attributes = array_merge($this->attributes, $attrs);
        $this->store->save($this);

        return $this;
    }

    public function delete(): void
    {
        $this->store->deleteById($this->id());
    }

    /** Reload this record's attributes from disk. */
    public function refresh(): static
    {
        if ($fresh = $this->store->find($this->id())) {
            $this->attributes = $fresh->attributes;
        }

        return $this;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
