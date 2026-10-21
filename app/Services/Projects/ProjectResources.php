<?php

namespace App\Services\Projects;

use Illuminate\Support\Str;

/**
 * What a project brings to every listing clip, a project usually being one
 * agent: the agent's details and photo, an agency logo, a music track and extra
 * images the planner may use. Kept in the project's vault (recursos/), so a
 * duplicated project takes them along. What a single clip uploads still wins.
 */
class ProjectResources
{
    public const AGENT_FIELDS = ['name', 'phone', 'email', 'licence'];

    public function __construct(private ProjectContext $context) {}

    /** @return array{name:string,phone:string,email:string,licence:string} */
    public function agent(): array
    {
        $saved = (array) ($this->read()['agent'] ?? []);

        return array_map(fn ($k) => trim((string) ($saved[$k] ?? '')), array_combine(self::AGENT_FIELDS, self::AGENT_FIELDS));
    }

    public function putAgent(array $agent): void
    {
        $this->write(['agent' => array_map(fn ($k) => trim((string) ($agent[$k] ?? '')), array_combine(self::AGENT_FIELDS, self::AGENT_FIELDS))]);
    }

    /** Music library track name ('' = the app's default listing music). */
    public function music(): string
    {
        return trim((string) ($this->read()['music'] ?? ''));
    }

    public function putMusic(string $track): void
    {
        $this->write(['music' => trim($track)]);
    }

    /** Absolute path of the agent's photo, or null. */
    public function agentPhotoPath(): ?string
    {
        return $this->file('agent-photo');
    }

    /** Absolute path of the logo that replaces the auto-found agency logo, or null. */
    public function logoPath(): ?string
    {
        return $this->file('logo');
    }

    /** @param 'agent-photo'|'logo' $name */
    public function putFile(string $name, string $sourcePath, string $ext): void
    {
        $this->deleteFile($name);
        @mkdir($this->dir(), 0775, true);
        copy($sourcePath, $this->dir().'/'.$name.'.'.strtolower($ext ?: 'jpg'));
    }

    /** @param 'agent-photo'|'logo' $name */
    public function deleteFile(string $name): void
    {
        foreach (glob($this->dir().'/'.$name.'.*') ?: [] as $f) {
            @unlink($f);
        }
    }

    /**
     * Extra images, each with what it shows (the planner reads that to place it).
     *
     * @return list<array{file:string,path:string,description:string}>
     */
    public function images(): array
    {
        return collect($this->read()['images'] ?? [])
            ->map(fn ($i) => ['file' => (string) $i['file'], 'path' => $this->dir().'/imagens/'.$i['file'], 'description' => (string) ($i['description'] ?? '')])
            ->filter(fn ($i) => is_file($i['path']))
            ->values()
            ->all();
    }

    public function addImage(string $sourcePath, string $ext, string $description): void
    {
        @mkdir($this->dir().'/imagens', 0775, true);
        $file = Str::slug(Str::limit($description, 40, '')) ?: 'imagem';
        $file .= '-'.Str::lower(Str::random(4)).'.'.strtolower($ext ?: 'jpg');
        copy($sourcePath, $this->dir().'/imagens/'.$file);
        $images = $this->read()['images'] ?? [];
        $images[] = ['file' => $file, 'description' => trim($description)];
        $this->write(['images' => $images]);
    }

    public function describeImage(string $file, string $description): void
    {
        $this->write(['images' => array_map(
            fn ($i) => $i['file'] === $file ? ['file' => $file, 'description' => trim($description)] : $i,
            $this->read()['images'] ?? [],
        )]);
    }

    public function removeImage(string $file): void
    {
        @unlink($this->dir().'/imagens/'.basename($file));
        $this->write(['images' => array_values(array_filter($this->read()['images'] ?? [], fn ($i) => $i['file'] !== $file))]);
    }

    private function dir(): string
    {
        return rtrim($this->context->vaultPath(), '/').'/recursos';
    }

    private function file(string $name): ?string
    {
        return (glob($this->dir().'/'.$name.'.*') ?: [])[0] ?? null;
    }

    private function read(): array
    {
        $f = $this->dir().'/recursos.json';

        return is_file($f) ? (array) json_decode((string) file_get_contents($f), true) : [];
    }

    /** Merge $changes into recursos.json. */
    private function write(array $changes): void
    {
        @mkdir($this->dir(), 0775, true);
        file_put_contents($this->dir().'/recursos.json', json_encode(array_merge($this->read(), $changes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
