<?php

namespace App\Services\Vault;

use Illuminate\Support\Collection;

interface VaultContract
{
    /** Lists all notes in a folder (recursive), sorted by date desc. */
    public function all(string $folder = '', bool $recursive = true): Collection;

    /** Gets a note by its relative path. */
    public function get(string $path): ?VaultNote;

    /** Creates/updates a note and returns the written relative path. */
    public function put(string $path, array $frontmatter, string $body): VaultNote;

    /** Creates a new note in a folder, generating a slug from the title. */
    public function create(string $folder, array $frontmatter, string $body = ''): VaultNote;

    /** Updates only the frontmatter of an existing note. */
    public function updateFrontmatter(string $path, array $patch): VaultNote;

    /** Removes a note. */
    public function delete(string $path): bool;

    /** Absolute path of the vault root. */
    public function root(): string;
}
