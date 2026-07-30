<?php

namespace App\Services\Aggregation;

/**
 * Thin boundary around the yt-dlp binary and simple HTTP requests.
 * Isolated to allow replacement by a test double (fake) in tests,
 * keeping the drivers verifiable without a network.
 */
interface YtDlpRunnerContract
{
    /**
     * Lists the recent items of a channel/playlist (lightweight metadata, no download).
     *
     * @return array{_type?:string,entries?:array<int,array<string,mixed>>}
     */
    public function listing(string $channelUrl, int $limit): array;

    /**
     * Full metadata of a single item (yt-dlp JSON), without downloading media.
     *
     * @return array<string,mixed>
     */
    public function metadata(string $url): array;

    /** Downloads the textual content of a URL (e.g. VTT subtitles). Returns null on failure. */
    public function fetch(string $url): ?string;
}
