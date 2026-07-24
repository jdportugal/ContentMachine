<?php

namespace App\Services\Settings;

use App\Services\Vault\VaultContract;

/**
 * Operational (non-secret) app settings, stored in the vault as a Markdown
 * note with frontmatter — readable in Obsidian and versionable.
 *
 * API KEYS stay in .env (secrets), never here.
 */
class SettingsRepository
{
    private const PATH = 'definicoes/definicoes.md';

    public function __construct(private readonly VaultContract $vault) {}

    /** Structure and default values. */
    public function defaults(): array
    {
        return [
            'geral' => [
                'nome_marca' => 'IATECA',
                'sitio' => '',
            ],
            'perfis' => [
                'youtube' => ['handle' => '', 'url' => ''],
                'instagram' => ['handle' => '', 'url' => ''],
                'tiktok' => ['handle' => '', 'url' => ''],
                'linkedin' => ['handle' => '', 'url' => ''],
            ],
            'agregador' => [
                'youtube' => [],   // channels to watch
                'reddit' => [],    // subreddits
                'twitter' => [],   // accounts
                'tiktok' => [],    // accounts
            ],
            // Channels to aggregate per platform (via yt-dlp). Seeds = Nick Saraev.
            'canais' => [
                'youtube' => ['https://www.youtube.com/@nicksaraev'],
                'instagram' => ['https://www.instagram.com/nick_saraev/'],
                'tiktok' => ['https://www.tiktok.com/@nick.saraev'],
                'linkedin' => ['https://www.linkedin.com/in/nick-saraev/'],
            ],
            'shorts' => [
                // Whisper model for local transcription (tiny|base|small|medium).
                // Empty → uses config/services (env WHISPER_MODEL).
                'whisper_model' => '',
            ],
        ];
    }

    /** All settings (defaults + stored). */
    public function all(): array
    {
        $nota = $this->vault->get(self::PATH);
        $guardadas = $nota?->frontmatter ?? [];

        // Remove system metadata that the vault may have added.
        unset($guardadas['data'], $guardadas['atualizado_em'], $guardadas['titulo'], $guardadas['tipo']);

        return array_replace_recursive($this->defaults(), $guardadas);
    }

    public function get(string $chave, mixed $default = null): mixed
    {
        return data_get($this->all(), $chave, $default);
    }

    /** Persists the settings, preserving missing defaults. */
    public function save(array $data): void
    {
        $limpo = array_replace_recursive($this->defaults(), $data);

        // The lists (channels/sources) are replaced wholesale — not merged
        // by index — so that removing or emptying an entry is respected
        // (array_replace_recursive alone would reintroduce the seeds).
        foreach (['canais', 'agregador'] as $grupo) {
            if (isset($data[$grupo]) && is_array($data[$grupo])) {
                foreach ($data[$grupo] as $chave => $lista) {
                    $limpo[$grupo][$chave] = array_values((array) $lista);
                }
            }
        }

        $frontmatter = array_merge([
            'titulo' => 'Definições',
            'tipo' => 'definicoes',
        ], $limpo);

        $this->vault->put(
            self::PATH,
            $frontmatter,
            "Definições operacionais da Máquina de Conteúdo.\n\n> As chaves de API vivem no `.env`, não neste ficheiro."
        );
    }
}
