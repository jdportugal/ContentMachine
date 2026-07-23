<?php

namespace App\Services\DesignSystem;

/**
 * Sistema de Design do CONTEÚDO gerado — a identidade visual/verbal que os
 * geradores (clips animados, publicações) devem seguir. Guardado como um
 * ficheiro Markdown simples no vault (o "cérebro"), legível no Obsidian e
 * versionável. Distinto do design da interface da app.
 *
 * A leitura é usada pelos prompts LLM dos geradores; a escrita vem do
 * separador "Sistema de Design". Ficheiro em falta = string vazia (sem erro),
 * espelhando o tratamento de `estilo-animacao.md`.
 */
class DesignSystemRepository
{
    /** Modelo inicial, escrito quando o ficheiro ainda não existe. */
    private const TEMPLATE = <<<'MD'
# Nebula — Sistema de Design

> Guia de marca para o **conteúdo gerado** (clips animados e publicações).
> Uma linguagem visual futurista: navy profundo, títulos em ouro fundido e
> tipografia condensada. Editável aqui e no Obsidian. Não afeta o design da
> própria aplicação.

## Voz e tom
- Português europeu, direto e encorajador. Sem jargão nem emojis.
- Frases curtas e orientadas à ação ("Começa a aprender", "Automatiza tudo").

## Identidade visual
- Paleta: fundo navy/void (#05060E, #0A1030), superfícies em painel #0C1225.
- Destaque principal: degradé de ouro fundido (#FFE59A → #FFB347 → #FF7A3D)
  aplicado aos títulos. Acento secundário: azul elétrico #2A3BEB.
- Texto: branco-estelar #EAF0FF; secundário #A9B6D6.
- Tipografia: **Anton** (display, MAIÚSCULAS, condensado) + **Space Grotesk** (corpo).
- Motivos: campos de estrelas (starfield), brilho suave, degradés radiais.

## Regras de composição
- Hierarquia clara: um foco por peça; título em ouro, corpo curto.
- Fundo mantém-se navy/void; a cor entra pelos títulos e por um único brilho.
- Espaço generoso; alinhamento por grelha.

## O que evitar
- Anton para texto de corpo; misturar vários tons de brilho na mesma peça.
- Cores de destaque a mais; clichés visuais e ruído decorativo sem função.
MD;

    public function path(): string
    {
        return (string) config('contentmachine.design_system.path');
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /** Conteúdo Markdown atual, ou '' se o ficheiro não existir. */
    public function read(): string
    {
        return $this->exists() ? (string) file_get_contents($this->path()) : '';
    }

    /** Conteúdo a usar nos geradores: o guardado, ou o modelo inicial. */
    public function readOrTemplate(): string
    {
        $conteudo = $this->read();

        return trim($conteudo) !== '' ? $conteudo : self::TEMPLATE;
    }

    /** Grava o Markdown no vault (criando a pasta se necessário). */
    public function write(string $conteudo): void
    {
        $path = $this->path();
        $dir = \dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($path, $conteudo);
    }

    /** Última modificação (H:i local), ou null se o ficheiro não existir. */
    public function updatedAt(): ?string
    {
        if (! $this->exists()) {
            return null;
        }

        return \Illuminate\Support\Carbon::createFromTimestamp(filemtime($this->path()))
            ->timezone(config('app.timezone'))
            ->translatedFormat('d/m/Y H:i');
    }

    // ---------------------------------------------------------------- tokens

    /** Caminho do JSON de tokens (tema) ao lado do design-system.md. */
    public function tokensPath(): string
    {
        return preg_replace('/\.md$/i', '', $this->path()).'.tokens.json';
    }

    public function tokensExist(): bool
    {
        return is_file($this->tokensPath());
    }

    /**
     * Tokens do tema guardados (para o renderizador). Null se ainda não foram
     * extraídos — o renderizador usa então os seus defaults (IATECA).
     *
     * @return array<string,mixed>|null
     */
    public function readTokens(): ?array
    {
        if (! $this->tokensExist()) {
            return null;
        }

        $dados = json_decode((string) file_get_contents($this->tokensPath()), true);

        return is_array($dados) ? $dados : null;
    }

    /** @param array<string,mixed> $tokens */
    public function writeTokens(array $tokens): void
    {
        $dir = \dirname($this->tokensPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $this->tokensPath(),
            json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    public function template(): string
    {
        return self::TEMPLATE;
    }
}
