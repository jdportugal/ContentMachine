<?php

namespace Tests\Feature;

use App\Services\Shorts\LocalVideoEngine;

/** Motor local falso para testes: sem ffmpeg, escreve ficheiros simulados. */
class FakeVideoEngine extends LocalVideoEngine
{
    public array $ultimaLegenda = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function resolveSource(string $ref, string $tempDir): string
    {
        return $ref;
    }

    public function probe(string $path): array
    {
        return ['duration' => 30.0, 'width' => 1080, 'height' => 1920, 'has_audio' => true];
    }

    /** How the last split() was asked to frame the cut. */
    public ?string $ultimoEnquadramento = null;

    public function split(string $source, float $startSec, float $endSec, string $dest, string $framing = 'original'): string
    {
        $this->ultimoEnquadramento = $framing;
        @mkdir(dirname($dest), 0775, true);
        file_put_contents($dest, 'RAW');

        return $dest;
    }

    public function burnSubtitles(string $clipPath, array $subtitleData, array $settings, string $wordMode, string $dest): string
    {
        $this->ultimaLegenda = $subtitleData;
        @mkdir(dirname($dest), 0775, true);
        file_put_contents($dest, 'FINAL');

        return $dest;
    }

    public function addMusic(string $videoPath, string $musicPath, array $settings, string $dest): string
    {
        @mkdir(dirname($dest), 0775, true);
        file_put_contents($dest, 'MUSIC');

        return $dest;
    }

    public function transcribe(string $videoPath, string $language = 'pt'): array
    {
        return [['start' => 0, 'end' => 1, 'text' => 'teste', 'words' => []]];
    }
}
