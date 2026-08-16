<?php

namespace App\Services\Clips\Store;

/**
 * A single animated-clip project, stored as a JSON file in the active project's
 * vault (clips-animados/<id>.json). Replaces the old Eloquent ClipProject —
 * same field names and helpers, so the pipeline reads the same. The DB is gone;
 * the vault is the source of truth (and it's per-project automatically).
 */
class ClipRecord
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_TRANSCRIBING = 'transcribing';

    public const STATUS_PLANNING = 'planning';

    // Plan is ready; waiting for the user to upload/keep-generated the suggested images.
    public const STATUS_COLLECTING = 'collecting';

    public const STATUS_RENDERING = 'rendering';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    public const TYPE_ANIMATION = 'animation';

    public const TYPE_OVERLAY = 'overlay';

    /** @param array<string,mixed> $attributes */
    public function __construct(private ClipStore $store, public array $attributes) {}

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

    /** String id (the vault filename). */
    public function id(): string
    {
        return (string) ($this->attributes['id'] ?? '');
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
        $this->store->delete($this->id);
    }

    /** Reload this record's attributes from disk. */
    public function refresh(): static
    {
        $this->attributes = $this->store->findOrFail($this->id())->attributes;

        return $this;
    }

    public function isActive(): bool
    {
        return ! in_array($this->attributes['status'] ?? null, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
