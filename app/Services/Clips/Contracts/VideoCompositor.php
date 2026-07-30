<?php

namespace App\Services\Clips\Contracts;

interface VideoCompositor
{
    /** Extract the audio track of a video to $outPath (wav). Returns the path. */
    public function extractAudio(string $videoPath, string $outPath): string;

    /** Overlay $overlay (with alpha) onto $baseVideo → $outPath (mp4). Returns the path. */
    public function overlay(string $baseVideo, string $overlay, string $outPath): string;

    /** Stack $topVideo over $bottomVideo (each half height) → $outPath. Audio from bottom. */
    public function splitStack(string $topVideo, string $bottomVideo, string $outPath): string;

    /** Duration of a video in seconds. */
    public function probeDuration(string $videoPath): float;
}
