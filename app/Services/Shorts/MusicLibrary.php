<?php

namespace App\Services\Shorts;

use Illuminate\Support\Str;

/**
 * Biblioteca de músicas de fundo para os shorts (pasta local
 * storage/app/shorts/musicas). Permite carregar faixas e escolher uma ao
 * acaso — o equivalente ao nó "List Music Files" + "Select Music" do fluxo
 * n8n (que listava uma pasta do Drive e sorteava uma faixa).
 */
class MusicLibrary
{
    /** Extensões de áudio aceites. */
    public const EXTENSOES = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'];

    public function __construct(private readonly string $dir) {}

    /** Caminho da pasta da biblioteca (criada se não existir). */
    public function dir(): string
    {
        if (! is_dir($this->dir)) {
            @mkdir($this->dir, 0775, true);
        }

        return $this->dir;
    }

    /**
     * Todas as faixas na biblioteca, por nome.
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
     * Copia um ficheiro para a biblioteca com um nome seguro e único.
     * Devolve o nome final (basename) gravado.
     */
    public function add(string $sourcePath, string $originalName): string
    {
        $nome = $this->nomeSeguro($originalName);
        copy($sourcePath, $this->dir().'/'.$nome);

        return $nome;
    }

    /** Caminho absoluto de uma faixa pelo nome, ou null se não existir. */
    public function pathFor(string $name): ?string
    {
        $p = $this->dir().'/'.basename($name);

        return is_file($p) ? $p : null;
    }

    /** Caminho de uma faixa escolhida ao acaso, ou null se a biblioteca estiver vazia. */
    public function randomPath(): ?string
    {
        $all = $this->all();

        if ($all === []) {
            return null;
        }

        return $all[random_int(0, count($all) - 1)]['path'];
    }

    /** Remove uma faixa pelo nome. */
    public function remove(string $name): bool
    {
        $p = $this->pathFor($name);

        return $p !== null && @unlink($p);
    }

    /** Gera um nome de ficheiro seguro e único dentro da pasta. */
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
