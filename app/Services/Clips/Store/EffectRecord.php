<?php

namespace App\Services\Clips\Store;

use App\Services\Avatars\AvatarStore;

/**
 * A custom SFX, stored as a JSON file in the active project's vault
 * (sfx/<id>.json). Keyed by a stable id; `slug` is the layer type. Replaces the
 * old Eloquent ClipEffect — same fields and helpers.
 *
 * Also reused verbatim for custom BACKGROUNDS (BackgroundStore) and VFX Lab
 * renders (VfxStore): identical shape, different vault dir — hence the store
 * union below — and for Avatar Gen lip-syncs (AvatarStore).
 */
class EffectRecord
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FAILED = 'failed';

    public const STATUS_UPDATING = 'updating';

    /** @param array<string,mixed> $attributes */
    public function __construct(private EffectStore|BackgroundStore|VfxStore|AvatarStore $store, public array $attributes) {}

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

    /**
     * Write these fields, merged onto what is CURRENTLY on disk — same reason as
     * ClipRecord::update(). The window is even wider here: GenerateEffectJob reads
     * the effect, then spends MINUTES writing and test-rendering a component
     * before it writes back. Merging onto its own snapshot meant an allow/disallow
     * toggle, an intro flag or a sound attached during that wait was silently
     * reverted the moment the generation landed.
     *
     * @param  array<string,mixed>  $attrs
     */
    public function update(array $attrs): static
    {
        $atual = $this->store->find($this->id())?->attributes ?? $this->attributes;
        $this->attributes = array_merge($atual, $attrs);
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
