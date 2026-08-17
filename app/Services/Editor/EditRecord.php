<?php

namespace App\Services\Editor;

/**
 * One video edit: two source tracks, a transcript, and the removals that turn
 * the raw take into the finished cut.
 */
class EditRecord
{
    /** @param array<string,mixed> $attributes */
    public function __construct(private EditorStore $store, public array $attributes) {}

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

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
        $this->store->deleteById($this->id());
    }

    /** @return array<int,array<string,mixed>> transcript segments */
    public function transcript(): array
    {
        $t = $this->attributes['transcript'] ?? [];

        return is_array($t) ? $t : [];
    }

    /** @return array<int,Removal> */
    public function removals(): array
    {
        $rows = $this->attributes['removals'] ?? [];

        return is_array($rows)
            ? array_values(array_map(fn ($r) => Removal::fromArray((array) $r), array_filter($rows, 'is_array')))
            : [];
    }

    /** @param array<int,Removal> $removals */
    public function setRemovals(array $removals): static
    {
        return $this->update([
            'removals' => array_values(array_map(fn (Removal $r) => $r->toArray(), $removals)),
        ]);
    }

    /** Is a given moment inside any removal? Used to strike the transcript. */
    public function isRemoved(float $at): bool
    {
        foreach ($this->removals() as $r) {
            if ($at >= $r->start && $at < $r->end) {
                return true;
            }
        }

        return false;
    }
}
