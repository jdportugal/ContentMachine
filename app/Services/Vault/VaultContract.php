<?php

namespace App\Services\Vault;

use Illuminate\Support\Collection;

interface VaultContract
{
    /** Lista todas as notas de uma pasta (recursivo), ordenadas por data desc. */
    public function all(string $folder = '', bool $recursive = true): Collection;

    /** Obtém uma nota pelo caminho relativo. */
    public function get(string $path): ?VaultNote;

    /** Cria/actualiza uma nota e devolve o caminho relativo escrito. */
    public function put(string $path, array $frontmatter, string $body): VaultNote;

    /** Cria uma nota nova numa pasta, gerando um slug a partir do título. */
    public function create(string $folder, array $frontmatter, string $body = ''): VaultNote;

    /** Actualiza apenas o frontmatter de uma nota existente. */
    public function updateFrontmatter(string $path, array $patch): VaultNote;

    /** Remove uma nota. */
    public function delete(string $path): bool;

    /** Caminho absoluto da raiz do vault. */
    public function root(): string;
}
