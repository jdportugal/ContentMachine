<?php

namespace App\Services\Guias;

use Illuminate\Support\Str;

/**
 * The /guias articles: one Markdown file each in resources/guias, with a short front matter
 * (titulo, descricao, palavra, resumo, publicado). The body is Markdown; its "Perguntas
 * frequentes" section (### pergunta + resposta) also feeds the FAQPage structured data.
 */
class Guias
{
    public const PASTA = 'guias';

    public const FAQ = 'Perguntas frequentes';

    public const TEMAS = ['Vídeo de imóveis', 'Portais', 'Redes sociais', 'Angariação', 'Marketing e marca', 'Inteligência artificial', 'Por agência', 'Outros'];

    private ?array $todos = null;

    public function __construct(private ?string $pasta = null)
    {
        $this->pasta ??= resource_path(self::PASTA);
    }

    /** @return array<string, array> every guide by slug, newest first, then by title */
    public function todos(): array
    {
        if ($this->todos !== null) {
            return $this->todos;
        }
        $guias = [];
        foreach (glob($this->pasta.'/*.md') ?: [] as $ficheiro) {
            $guia = $this->ler($ficheiro);
            if ($guia !== null) {
                $guias[$guia['slug']] = $guia;
            }
        }
        uasort($guias, fn ($a, $b) => [$b['publicado'], $a['titulo']] <=> [$a['publicado'], $b['titulo']]);

        return $this->todos = $guias;
    }

    /** The themes in reading order, each with its guides. @return array<string, list<array>> */
    public function porTema(): array
    {
        $temas = array_fill_keys(self::TEMAS, []);
        foreach ($this->todos() as $g) {
            $temas[$g['tema']][] = $g;
        }

        return array_filter($temas);
    }

    /** Up to $n other guides, from the same theme first. @return list<array> */
    public function relacionados(array $guia, int $n = 4): array
    {
        $outros = array_filter($this->todos(), fn ($g) => $g['slug'] !== $guia['slug']);
        usort($outros, fn ($a, $b) => ($b['tema'] === $guia['tema']) <=> ($a['tema'] === $guia['tema']));

        return array_slice($outros, 0, $n);
    }

    public function encontrar(string $slug): ?array
    {
        return $this->todos()[$slug] ?? null;
    }

    private function ler(string $ficheiro): ?array
    {
        $texto = str_replace("\r\n", "\n", (string) file_get_contents($ficheiro));
        if (! preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $texto, $m)) {
            return null;
        }
        $meta = [];
        foreach (explode("\n", $m[1]) as $linha) {
            if (preg_match('/^([a-z_]+):\s*(.*)$/', $linha, $par)) {
                $meta[$par[1]] = trim($par[2]);
            }
        }
        if (empty($meta['titulo']) || empty($meta['descricao'])) {
            return null;
        }
        $corpo = trim($m[2]);

        return [
            'slug' => basename($ficheiro, '.md'),
            'titulo' => $meta['titulo'],
            'descricao' => $meta['descricao'],
            'palavra' => $meta['palavra'] ?? '',
            'tema' => $meta['tema'] ?? 'Outros',
            'agencia' => $meta['agencia'] ?? null,
            'resumo' => $meta['resumo'] ?? '',
            'publicado' => $meta['publicado'] ?? date('Y-m-d', (int) filemtime($ficheiro)),
            'atualizado' => $meta['atualizado'] ?? ($meta['publicado'] ?? date('Y-m-d', (int) filemtime($ficheiro))),
            'html' => self::html($corpo),
            'faq' => self::faq($corpo),
            'palavras' => str_word_count(strip_tags(Str::markdown($corpo)), 0, 'ÀÁÂÃÇÉÊÍÓÔÕÚàáâãçéêíóôõú'),
        ];
    }

    /** The body as HTML: headings get ids (for links to a section) and outside links open safely. */
    public static function html(string $markdown): string
    {
        $html = Str::markdown($markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        $html = preg_replace_callback('#<h([23])>(.*?)</h\1>#s', fn ($m) => '<h'.$m[1].' id="'.Str::slug(html_entity_decode(strip_tags($m[2]))).'">'.$m[2].'</h'.$m[1].'>', $html);

        return preg_replace('#<a href="(https?://[^"]+)">#', '<a href="$1" target="_blank" rel="noopener">', $html);
    }

    /** @return list<array{pergunta: string, resposta: string}> from the "Perguntas frequentes" section */
    public static function faq(string $markdown): array
    {
        if (! preg_match('/^## '.preg_quote(self::FAQ, '/').'\s*\n(.*?)(?=^## |\z)/ms', $markdown, $m)) {
            return [];
        }
        preg_match_all('/^### (.+)\n+(.*?)(?=^### |\z)/ms', $m[1], $pares, PREG_SET_ORDER);

        return array_map(fn ($p) => [
            'pergunta' => trim($p[1]),
            'resposta' => trim(preg_replace('/\s+/', ' ', strip_tags(Str::markdown(trim($p[2]))))),
        ], $pares);
    }
}
