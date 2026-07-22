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
# Sistema de Design — IATECA

> Guia de marca para o **conteúdo gerado** (clips animados e publicações).
> Editável aqui e no Obsidian. Não afeta o design da própria aplicação.

## Voz e tom
- Português europeu, sóbrio e culto. Sem emojis.
- Frases curtas e afirmativas; evita marketês e superlativos.

## Identidade visual
- Paleta: descreva as cores da marca (fundo, tinta, destaque).
- Tipografia: famílias de display e de corpo.
- Motivos: elementos recorrentes (fleurões, molduras, texturas).

## Regras de composição
- Hierarquia clara: um foco por peça.
- Espaço em branco generoso; nada sobrecarregado.

## O que evitar
- Clichés visuais, stock genérico, ruído decorativo sem função.
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

    public function template(): string
    {
        return self::TEMPLATE;
    }
}
