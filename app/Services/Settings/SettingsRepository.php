<?php

namespace App\Services\Settings;

use App\Services\Vault\VaultContract;

/**
 * Operational app settings, stored in the vault as a Markdown note with
 * frontmatter — readable in Obsidian and versionable.
 *
 * For LOCAL use, API keys and service/model config live here too (`chaves`,
 * `modelos`) so everything is editable in-app instead of the `.env`. The
 * SettingsOverlayProvider maps them onto config() at boot; an empty value
 * falls back to whatever the `.env`/config default provides. Do not enable
 * vault sync to a public location with real keys in it.
 */
class SettingsRepository
{
    private const PATH = 'definicoes/definicoes.md';

    public function __construct(private readonly VaultContract $vault, private readonly SharedKeys $keys) {}

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
            // API keys (local use). Empty → falls back to the .env value.
            'chaves' => [
                'anthropic' => '',
                'openai' => '',
                'gemini' => '',
                'apify' => '',
                'tubelab' => '',
                'elevenlabs' => '',
                'youtube' => '',
                'reddit_client_id' => '',
                'reddit_client_secret' => '',
                'kie' => '',
                'blotato' => '',
            ],
            // Blotato connected-account ids per platform (copied from the Blotato
            // dashboard). Empty → that platform can't be posted to.
            'blotato' => [
                'youtube' => '',
                'instagram' => '',
                'tiktok' => '',
                'linkedin' => '',
                'threads' => '',
            ],
            // Service/model config. Empty → falls back to the .env/config default.
            'modelos' => [
                'llm_provider' => '',        // auto | claude-cli | anthropic | openai | gemini | none
                'anthropic_model' => '',
                'openai_model' => '',
                'gemini_model' => '',
                'aggregation_limit' => '',   // videos per channel
                'aggregation_timeout' => '', // seconds per yt-dlp call
                'elevenlabs_voice' => '',    // ElevenLabs voice id for the clip voiceover
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

        $merged = array_replace_recursive($this->defaults(), $guardadas);
        // API keys are GLOBAL (shared across projects) — override any per-project copy.
        $merged['chaves'] = array_replace($this->defaults()['chaves'], $this->keys->all());

        return $merged;
    }

    public function get(string $chave, mixed $default = null): mixed
    {
        return data_get($this->all(), $chave, $default);
    }

    /**
     * Persists the settings. Merges onto the CURRENTLY stored values (not just
     * the defaults), so a partial save updates only the groups it includes and
     * never wipes the others.
     */
    public function save(array $data): void
    {
        // API keys go to the global shared store; everything else is per-project.
        if (isset($data['chaves']) && is_array($data['chaves'])) {
            $this->keys->save($data['chaves']);
            unset($data['chaves']);
        }

        $limpo = array_replace_recursive($this->all(), $data);
        unset($limpo['chaves']); // never persist keys in the per-project vault note

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
            "Definições operacionais da Máquina de Conteúdo.\n\n> As chaves de API são partilhadas entre projetos (guardadas fora do vault), não neste ficheiro."
        );
    }
}
