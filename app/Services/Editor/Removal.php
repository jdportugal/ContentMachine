<?php

namespace App\Services\Editor;

/**
 * One span of the recording to drop, in seconds on the ORIGINAL timeline.
 *
 * Removals — rather than keep-ranges — are the stored unit so that a detector's
 * proposal and a hand edit are the same kind of object, and "restore this" is
 * just deleting one.
 */
class Removal
{
    public const SILENCE = 'silence';

    public const DUPLICATE = 'duplicate';

    public const MANUAL = 'manual';

    public function __construct(
        public readonly float $start,
        public readonly float $end,
        public readonly string $reason = self::MANUAL,
        /** Free text for the UI: the words dropped, or why. */
        public readonly string $note = '',
    ) {}

    public function duration(): float
    {
        return max(0.0, $this->end - $this->start);
    }

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            (float) ($row['start'] ?? 0),
            (float) ($row['end'] ?? 0),
            (string) ($row['reason'] ?? self::MANUAL),
            (string) ($row['note'] ?? ''),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'start' => round($this->start, 3),
            'end' => round($this->end, 3),
            'reason' => $this->reason,
            'note' => $this->note,
        ];
    }
}
