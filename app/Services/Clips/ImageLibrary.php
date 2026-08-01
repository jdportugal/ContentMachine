<?php

namespace App\Services\Clips;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Per-project library of reusable images (logos, brand shots, screenshots). Each
 * image is a file plus a JSON sidecar in the ACTIVE project's vault
 * (image-library/<id>.{ext,json}); no database, per-project automatically. The clip
 * planner searches this first (ImageLibraryMatcher) so a scene that needs, say, a
 * Gronk logo reuses one already here instead of asking the user or generating.
 *
 * A matched image is copied into the clip's uploads via attachToClip(), producing
 * the same {id,path,description,tone,transparent} entry as an uploaded image, so the
 * render pipeline is unchanged.
 */
class ImageLibrary
{
    /** Accepted image extensions. */
    public const EXTENSOES = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'];

    public function __construct(private ProjectContext $context) {}

    public function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/image-library';
    }

    /**
     * Every image in the library, newest first.
     *
     * @return array<int,array{id:string,name:string,description:string,ext:string,path:string,transparent:bool,tone:string}>
     */
    public function all(): array
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return [];
        }
        $out = [];
        foreach (glob($dir.'/*.json') ?: [] as $meta) {
            $entry = $this->hydrate($meta);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }
        usort($out, fn ($a, $b) => strcmp($b['id'], $a['id']));

        return $out;
    }

    public function isEmpty(): bool
    {
        return $this->all() === [];
    }

    /** @return array{id:string,name:string,description:string,ext:string,path:string,transparent:bool,tone:string}|null */
    public function find(string $id): ?array
    {
        $meta = $this->dir().'/'.basename($id).'.json';

        return is_file($meta) ? $this->hydrate($meta) : null;
    }

    /**
     * Copy a file into the library, probing it for tone/transparency and defaulting
     * the description to the (humanised) original filename. Returns the new entry.
     *
     * @return array{id:string,name:string,description:string,ext:string,path:string,transparent:bool,tone:string}
     */
    public function add(string $sourcePath, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (! in_array($ext, self::EXTENSOES, true)) {
            $ext = 'png';
        }
        $name = trim(Str::of(pathinfo($originalName, PATHINFO_FILENAME))->replace(['-', '_'], ' ')->squish());
        $name = $name === '' ? 'image' : $name;

        @mkdir($this->dir(), 0775, true);
        $id = $this->uniqueId();
        $file = $this->dir().'/'.$id.'.'.$ext;
        copy($sourcePath, $file);

        $entry = [
            'id' => $id,
            'name' => $name,
            'description' => $name,
            'ext' => $ext,
            'path' => $file,
            'transparent' => ImageProbe::hasAlpha($file),
            'tone' => ImageProbe::tone($file),
        ];
        $this->writeMeta($entry);

        return $entry;
    }

    public function updateDescription(string $id, string $description): void
    {
        $entry = $this->find($id);
        if ($entry !== null) {
            $entry['description'] = trim($description);
            $this->writeMeta($entry);
        }
    }

    public function remove(string $id): void
    {
        $entry = $this->find($id);
        @unlink($this->dir().'/'.basename($id).'.json');
        if ($entry !== null && is_file($entry['path'])) {
            @unlink($entry['path']);
        }
    }

    /**
     * Copy a library image into the clip's uploads so it renders like any provided
     * image. Returns a clip-image entry (or null if the image is gone).
     *
     * @return array{id:string,path:string,description:string,tone:string,transparent:bool,library:bool}|null
     */
    public function attachToClip(string $id): ?array
    {
        $img = $this->find($id);
        if ($img === null || ! is_file($img['path'])) {
            return null;
        }
        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $rel = 'clips/uploads/'.Str::lower(Str::random(12)).'.'.$img['ext'];
        $disk->put($rel, (string) file_get_contents($img['path']));

        return [
            // img_ prefix: the rest of the pipeline treats it as a provided image.
            'id' => 'img_'.substr(md5($rel), 0, 8),
            'path' => $rel,
            'description' => $img['description'] ?: $img['name'],
            'tone' => $img['tone'],
            'transparent' => $img['transparent'],
            'library' => true,
        ];
    }

    /** @return array{id:string,name:string,description:string,ext:string,path:string,transparent:bool,tone:string}|null */
    private function hydrate(string $metaFile): ?array
    {
        $data = json_decode((string) file_get_contents($metaFile), true);
        if (! is_array($data) || empty($data['id'])) {
            return null;
        }
        $data['path'] = $this->dir().'/'.$data['id'].'.'.($data['ext'] ?? 'png');
        if (! is_file($data['path'])) {
            return null; // metadata orphaned from its file
        }

        return $data;
    }

    private function writeMeta(array $entry): void
    {
        unset($entry['path']); // derived from id+ext on read
        @mkdir($this->dir(), 0775, true);
        file_put_contents(
            $this->dir().'/'.$entry['id'].'.json',
            json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }

    private function uniqueId(): string
    {
        do {
            $id = 'lib-'.Str::lower(Str::random(8));
        } while (is_file($this->dir().'/'.$id.'.json'));

        return $id;
    }
}
