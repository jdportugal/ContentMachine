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

    // A listing URL being scraped, scripted and narrated (ImportListingJob).
    public const STATUS_IMPORTING = 'importing';

    // Several voiceover takes are ready; waiting for the user to pick one by ear.
    public const STATUS_CHOOSING_VOICE = 'choosing_voice';

    // Property walkthrough: rooms being found in the footage and staged (AnalyzePropertyJob).
    public const STATUS_ANALYZING = 'analyzing';

    // The walkthrough is cleaned up; waiting for the user to mark the sections to animate.
    public const STATUS_CHOOSING_SECTIONS = 'choosing_sections';

    // Staged room stills are ready; waiting for the user to approve, restage or skip each.
    public const STATUS_REVIEWING_ROOMS = 'reviewing_rooms';

    // Kling is animating the approved rooms; then the walkthrough is cut and narrated.
    public const STATUS_ANIMATING = 'animating';

    public const STATUS_TRANSCRIBING = 'transcribing';

    public const STATUS_PLANNING = 'planning';

    // Plan is ready; waiting for the user to upload/keep-generated the suggested images.
    public const STATUS_COLLECTING = 'collecting';

    // "Improve with AI": the marked videos are being edited (Wan 2.7 Video Edit), then rendered.
    public const STATUS_IMPROVING = 'improving';

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

    /**
     * Write these fields, merged onto what is CURRENTLY on disk.
     *
     * save() persists the whole record, so merging onto this instance's own
     * snapshot meant a writer also put back every field as it was when it read —
     * silently undoing anything that happened in between. That is a real window,
     * not a theoretical one: GenerateRequestImageJob reads the clip, spends up to
     * a minute in the image generator, then writes. A user who pressed "continue"
     * during that minute had their status:planning reverted to collecting by the
     * finishing job and was bounced back onto the collect screen, images already
     * populated; an edited prompt or another suggestion's upload went the same way.
     *
     * A caller only ever means to write the fields it passes, so those win and
     * everything else keeps the current value.
     *
     * ponytail: last-writer-wins per FIELD — two jobs racing on the same key still
     * settle arbitrarily. Needs file locking if that ever matters.
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

    /** Where a clip's karaoke captions can sit, or 'none' to hide them. */
    public const CAPTIONS = ['bottom', 'middle', 'top', 'none'];

    /**
     * "No images": the clip is planned without any image layer at all — nothing is
     * generated, nothing is filmed, and the user is never asked to supply a picture.
     */
    public function semImagens(): bool
    {
        return (bool) ($this->attributes['meta']['no_images'] ?? false);
    }

    /**
     * "Only reference images": the clip is built from the user's own images —
     * every one of them shown, and nothing generated, fetched or filmed.
     */
    public function soReferencias(): bool
    {
        return (bool) ($this->attributes['meta']['only_reference_images'] ?? false);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }
}
