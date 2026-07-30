<?php

namespace App\Services\Shorts;

use Illuminate\Support\Str;

/**
 * Background music library for the shorts (local folder
 * storage/app/shorts/musicas). Allows loading tracks and picking one at
 * random — the equivalent of the "List Music Files" + "Select Music" nodes from the
 * n8n flow (which listed a Drive folder and drew a random track).
 */
class MusicLibrary
{
    /** Accepted audio extensions. */
    public const EXTENSOES = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'];

    public function __construct(private readonly string $dir) {}

    /** Path of the library folder (created if it does not exist). */
    public function dir(): string
    {
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }

        return $this->dir;
    }

    /**
     * All the tracks in the library, by name.
     *
     * @return array<int,array{name:string,path:string,size:int}>
     */
    public function all(): array
    {
        $out = [];
        foreach (glob($this->dir().'/*') ?: [] as $f) {
            if (is_file($f) && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::EXTENSOES, true)) {
                $out[] = ['name' => basename($f), 'path' => $f, 'size' => (int) filesize($f)];
            }
        }

        usort($out, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $out;
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /**
     * Copies a file into the library with a safe and unique name.
     * Returns the final name (basename) written.
     */
    public function add(string $sourcePath, string $originalName): string
    {
        $nome = $this->nomeSeguro($originalName);
        copy($sourcePath, $this->dir().'/'.$nome);

        return $nome;
    }

    /** Absolute path of a track by name, or null if it does not exist. */
    public function pathFor(string $name): ?string
    {
        $p = $this->dir().'/'.basename($name);

        return is_file($p) ? $p : null;
    }

    /** Path of a randomly chosen track, or null if the library is empty. */
    public function randomPath(): ?string
    {
        $all = $this->all();

        if ($all === []) {
            return null;
        }

        return $all[random_int(0, count($all) - 1)]['path'];
    }

    /** Removes a track by name. */
    public function remove(string $name): bool
    {
        $p = $this->pathFor($name);

        return $p !== null && @unlink($p);
    }

    /** Generates a safe and unique filename within the folder. */
    private function nomeSeguro(string $original): string
    {
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSOES, true)) {
            $ext = 'mp3';
        }

        $slug = Str::slug(pathinfo($original, PATHINFO_FILENAME)) ?: 'musica';

        $nome = $slug.'.'.$ext;
        $i = 1;
        while (is_file($this->dir().'/'.$nome)) {
            $nome = $slug.'-'.$i.'.'.$ext;
            $i++;
        }

        return $nome;
    }
}
