<?php

namespace App\Services\Settings;

use App\Services\Vault\VaultContract;

/**
 * Definições operacionais da aplicação (não-secretas), guardadas no vault
 * como uma nota Markdown com frontmatter — legível no Obsidian e versionável.
 *
 * As CHAVES DE API continuam no .env (segredos), nunca aqui.
 */
class SettingsRepository
{
    private const PATH = 'definicoes/definicoes.md';

    public function __construct(private readonly VaultContract $vault) {}

    /** Estrutura e valores por defeito. */
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
                'youtube' => [],   // canais a vigiar
                'reddit' => [],    // subreddits
                'twitter' => [],   // contas
                'tiktok' => [],    // contas
            ],
            // Canais a agregar por plataforma (via yt-dlp). Sementes = Nick Saraev.
            'canais' => [
                'youtube' => ['https://www.youtube.com/@nicksaraev'],
                'instagram' => ['https://www.instagram.com/nick_saraev/'],
                'tiktok' => ['https://www.tiktok.com/@nick.saraev'],
                'linkedin' => ['https://www.linkedin.com/in/nick-saraev/'],
            ],
            'shorts' => [
                // Modelo Whisper para transcrição local (tiny|base|small|medium).
                // Vazio → usa config/services (env WHISPER_MODEL).
                'whisper_model' => '',
            ],
        ];
    }

    /** Todas as definições (defaults + guardadas). */
    public function all(): array
    {
        $nota = $this->vault->get(self::PATH);
        $guardadas = $nota?->frontmatter ?? [];

        // Remove metadados de sistema que o vault possa ter acrescentado.
        unset($guardadas['data'], $guardadas['atualizado_em'], $guardadas['titulo'], $guardadas['tipo']);

        return array_replace_recursive($this->defaults(), $guardadas);
    }

    public function get(string $chave, mixed $default = null): mixed
    {
        return data_get($this->all(), $chave, $default);
    }

    /** Persiste as definições, preservando os defaults em falta. */
    public function save(array $data): void
    {
        $limpo = array_replace_recursive($this->defaults(), $data);

        // As listas (canais/fontes) são substituídas por inteiro — não fundidas
        // por índice — para que remover ou esvaziar uma entrada seja respeitado
        // (o array_replace_recursive, sozinho, reintroduziria as sementes).
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
