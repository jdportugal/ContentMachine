<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;

/**
 * Renderizador SVG determinístico e sem dependências — o análogo offline do
 * gerador de imagens do AdsMaker. Desenha cada cartão num gabarito da linha
 * gráfica «nocturna» da IATECA (pergaminho, tinta, ouro, verde-azulado).
 *
 * Puro: não toca no sistema de ficheiros; devolve markup SVG por cartão.
 */
class SvgSlideRenderer implements SlideRenderer
{
    private const BG = '#efe6d2';       // pergaminho
    private const BG2 = '#e7dcc4';      // pergaminho escuro (fundo capa)
    private const INK = '#2b2822';      // tinta
    private const SOFT = '#6b6152';     // tinta suave
    private const FAINT = '#a99f8c';    // tinta ténue
    private const GOLD = '#c89b3c';     // ouro
    private const TEAL = '#1f7a7a';     // verde-azulado

    private const DISPLAY = "Georgia, 'Times New Roman', serif";
    private const MONO = "'Courier New', monospace";

    public function render(PublicacaoPlan $plan, array $kind): array
    {
        [$w, $h] = $this->dimensoes((string) ($kind['proporcao'] ?? '1:1'));
        $gabarito = (string) ($kind['gabarito'] ?? 'quadrado');
        $formato = (string) ($kind['formato'] ?? 'single');
        $accent = (string) ($kind['_cor'] ?? self::TEAL);
        $total = count($plan->slides);

        $svgs = [];
        foreach ($plan->slides as $i => $slide) {
            $svgs[] = $this->cartao($slide, $gabarito, $formato, $w, $h, $i, $total, $accent);
        }

        return $svgs;
    }

    // ----------------------------------------------------------------- cartão

    private function cartao(SlidePlano $s, string $gabarito, string $formato, int $w, int $h, int $idx, int $total, string $accent): string
    {
        $capa = $formato === 'carousel' && $idx === 0;
        $fundo = $capa ? self::BG2 : self::BG;

        $conteudo = match ($gabarito) {
            'citacao' => $this->gabaritoCitacao($s, $w, $h, $accent),
            'dica' => $this->gabaritoDica($s, $w, $h, $accent),
            'lista' => $this->gabaritoLista($s, $w, $h, $idx, $capa, $accent),
            'capa-conteudo' => $this->gabaritoCapaConteudo($s, $w, $h, $idx, $capa, $accent),
            default => $this->gabaritoQuadrado($s, $w, $h, $accent),
        };

        $moldura = $this->moldura($w, $h, $accent);
        $rodape = $this->rodape($w, $h, $idx, $total, $formato, $accent);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}" font-family="{$this->esc(self::DISPLAY)}">
        <rect width="{$w}" height="{$h}" fill="{$fundo}"/>
        {$moldura}
        {$conteudo}
        {$rodape}
        </svg>
        SVG;
    }

    /** Moldura gravada interior + cantos. */
    private function moldura(int $w, int $h, string $accent): string
    {
        $m = 56;
        $iw = $w - 2 * $m;
        $ih = $h - 2 * $m;
        $g = self::GOLD;

        return <<<SVG
        <rect x="{$m}" y="{$m}" width="{$iw}" height="{$ih}" fill="none" stroke="{$g}" stroke-opacity="0.55" stroke-width="2"/>
        <rect x="{$this->n($m + 10)}" y="{$this->n($m + 10)}" width="{$this->n($iw - 20)}" height="{$this->n($ih - 20)}" fill="none" stroke="{$accent}" stroke-opacity="0.25" stroke-width="1"/>
        SVG;
    }

    /** Rodapé: cota da biblioteca + índice do cartão (nos carrosséis). */
    private function rodape(int $w, int $h, int $idx, int $total, string $formato, string $accent): string
    {
        $y = $h - 84;
        $x = 92;
        $cota = 'IATECA · 686.2 · IAT';
        $svg = '<text x="'.$x.'" y="'.$y.'" font-family="'.$this->esc(self::MONO).'" font-size="20" letter-spacing="2" fill="'.self::FAINT.'">'.$this->esc($cota).'</text>';

        if ($formato === 'carousel') {
            $marca = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT).' / '.str_pad((string) $total, 2, '0', STR_PAD_LEFT);
            $svg .= '<text x="'.($w - $x).'" y="'.$y.'" text-anchor="end" font-family="'.$this->esc(self::MONO).'" font-size="20" letter-spacing="2" fill="'.$accent.'">'.$this->esc($marca).'</text>';
        }

        return $svg;
    }

    // ---------------------------------------------------------------- gabaritos

    private function gabaritoQuadrado(SlidePlano $s, int $w, int $h, string $accent): string
    {
        $cx = $w / 2;
        $eyebrow = $this->eyebrow('EX · LIBRIS', $cx, 200, $accent);
        $titulo = $this->titulo($s->titulo ?: 'Sabia que…', $cx, 320, $w, 78, 16);
        $fleuron = $this->fleuron($cx, $h / 2 + 30, $accent);
        $corpo = $this->paragrafo($s->texto, $cx, $h / 2 + 120, $w, 34, 26);

        return $eyebrow.$titulo.$fleuron.$corpo;
    }

    private function gabaritoCitacao(SlidePlano $s, int $w, int $h, string $accent): string
    {
        $cx = $w / 2;
        $aspas = '<text x="'.$cx.'" y="300" text-anchor="middle" font-size="220" fill="'.self::GOLD.'" fill-opacity="0.7" font-family="'.$this->esc(self::DISPLAY).'">&#8220;</text>';
        $quote = $this->titulo($s->titulo ?: 'A citação', $cx, $h / 2 - 40, $w, 68, 22, true);
        $fleuron = $this->fleuron($cx, $h / 2 + 130, $accent);
        $autor = $this->eyebrow($s->texto !== '' ? $s->texto : 'Anónimo', $cx, $h / 2 + 210, $accent);

        return $aspas.$quote.$fleuron.$autor;
    }

    private function gabaritoDica(SlidePlano $s, int $w, int $h, string $accent): string
    {
        $cx = $w / 2;
        $selo = '<circle cx="'.$cx.'" cy="230" r="52" fill="none" stroke="'.$accent.'" stroke-width="2"/>'
            .'<text x="'.$cx.'" y="252" text-anchor="middle" font-size="52" fill="'.self::GOLD.'">&#10022;</text>';
        $eyebrow = $this->eyebrow('DICA RÁPIDA', $cx, 360, $accent);
        $titulo = $this->titulo($s->titulo ?: 'Uma dica', $cx, 470, $w, 72, 16);
        $corpo = $this->paragrafo($s->texto, $cx, $h / 2 + 200, $w, 36, 26);

        return $selo.$eyebrow.$titulo.$corpo;
    }

    private function gabaritoCapaConteudo(SlidePlano $s, int $w, int $h, int $idx, bool $capa, string $accent): string
    {
        $cx = $w / 2;

        if ($capa) {
            $eyebrow = $this->eyebrow('CARROSSEL', $cx, 300, $accent);
            $titulo = $this->titulo($s->titulo ?: 'Capa', $cx, 470, $w, 92, 16);
            $fleuron = $this->fleuron($cx, $h - 360, $accent);
            $sub = $this->paragrafo($s->texto, $cx, $h - 300, $w, 34, 24);
            $desliza = '<text x="'.$cx.'" y="'.($h - 150).'" text-anchor="middle" font-family="'.$this->esc(self::MONO).'" font-size="22" letter-spacing="3" fill="'.$accent.'">DESLIZE &#8594;</text>';

            return $eyebrow.$titulo.$fleuron.$sub.$desliza;
        }

        $badge = $this->badgeNumero($idx + 1, 150, 220, $accent);
        $titulo = $this->titulo($s->titulo ?: 'Cartão', 92, 400, $w - 40, 64, 20, false, 'start');
        $corpo = $this->paragrafo($s->texto, 92, 540, $w - 40, 38, 30, 'start');

        return $badge.$titulo.$corpo;
    }

    private function gabaritoLista(SlidePlano $s, int $w, int $h, int $idx, bool $capa, string $accent): string
    {
        $cx = $w / 2;

        if ($capa) {
            $eyebrow = $this->eyebrow('LISTA', $cx, 300, $accent);
            $titulo = $this->titulo($s->titulo ?: 'A lista', $cx, 480, $w, 90, 16);
            $fleuron = $this->fleuron($cx, $h - 320, $accent);

            return $eyebrow.$titulo.$fleuron;
        }

        $numero = '<text x="130" y="300" font-size="150" font-family="'.$this->esc(self::DISPLAY).'" fill="'.self::GOLD.'" fill-opacity="0.85">'.$idx.'</text>';
        $regua = '<line x1="92" y1="340" x2="'.($w - 92).'" y2="340" stroke="'.$accent.'" stroke-opacity="0.4" stroke-width="2"/>';
        $titulo = $this->titulo($s->titulo ?: 'Item', 92, 440, $w - 40, 64, 20, false, 'start');
        $corpo = $this->paragrafo($s->texto, 92, 580, $w - 40, 38, 30, 'start');

        return $numero.$regua.$titulo.$corpo;
    }

    // ------------------------------------------------------------- primitivas

    private function eyebrow(string $texto, float $x, float $y, string $accent): string
    {
        return '<text x="'.$this->n($x).'" y="'.$this->n($y).'" text-anchor="middle" font-family="'.$this->esc(self::MONO).'" font-size="24" letter-spacing="6" fill="'.$accent.'">'.$this->esc(mb_strtoupper($texto)).'</text>';
    }

    private function badgeNumero(int $n, float $x, float $y, string $accent): string
    {
        return '<circle cx="'.$this->n($x).'" cy="'.$this->n($y).'" r="46" fill="'.$accent.'"/>'
            .'<text x="'.$this->n($x).'" y="'.$this->n($y + 16).'" text-anchor="middle" font-size="46" fill="'.self::BG.'" font-family="'.$this->esc(self::DISPLAY).'">'.$n.'</text>';
    }

    private function fleuron(float $cx, float $y, string $accent): string
    {
        return '<text x="'.$this->n($cx).'" y="'.$this->n($y).'" text-anchor="middle" font-size="44" fill="'.self::GOLD.'">&#10087;</text>';
    }

    /**
     * Título com quebra em várias linhas (tspan). $anchor 'middle' (por
     * omissão) centra em $x; 'start' alinha à esquerda a partir de $x.
     */
    private function titulo(string $texto, float $x, float $y, int $w, int $tamanho, int $maxChars, bool $italico = false, string $anchor = 'middle'): string
    {
        $linhas = $this->quebrar($texto, $maxChars);
        $altura = $tamanho + 12;
        $estilo = $italico ? ' font-style="italic"' : '';
        $tspans = '';
        foreach ($linhas as $i => $linha) {
            $tspans .= '<tspan x="'.$this->n($x).'" dy="'.($i === 0 ? 0 : $altura).'">'.$this->esc($linha).'</tspan>';
        }

        return '<text x="'.$this->n($x).'" y="'.$this->n($y).'" text-anchor="'.$anchor.'" font-family="'.$this->esc(self::DISPLAY).'" font-size="'.$tamanho.'"'.$estilo.' fill="'.self::INK.'" font-weight="600">'.$tspans.'</text>';
    }

    private function paragrafo(string $texto, float $x, float $y, int $w, int $tamanho, int $maxChars, string $anchor = 'middle'): string
    {
        if (trim($texto) === '') {
            return '';
        }

        $linhas = array_slice($this->quebrar($texto, $maxChars), 0, 6);
        $altura = $tamanho + 14;
        $tspans = '';
        foreach ($linhas as $i => $linha) {
            $tspans .= '<tspan x="'.$this->n($x).'" dy="'.($i === 0 ? 0 : $altura).'">'.$this->esc($linha).'</tspan>';
        }

        return '<text x="'.$this->n($x).'" y="'.$this->n($y).'" text-anchor="'.$anchor.'" font-family="'.$this->esc(self::DISPLAY).'" font-size="'.$tamanho.'" fill="'.self::SOFT.'">'.$tspans.'</text>';
    }

    /** Quebra o texto em linhas de no máximo ~$maxChars caracteres (por palavras). */
    private function quebrar(string $texto, int $maxChars): array
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') {
            return [''];
        }

        $palavras = explode(' ', $texto);
        $linhas = [];
        $atual = '';

        foreach ($palavras as $palavra) {
            $tentativa = $atual === '' ? $palavra : $atual.' '.$palavra;
            if (mb_strlen($tentativa) > $maxChars && $atual !== '') {
                $linhas[] = $atual;
                $atual = $palavra;
            } else {
                $atual = $tentativa;
            }
        }
        if ($atual !== '') {
            $linhas[] = $atual;
        }

        return $linhas;
    }

    private function dimensoes(string $proporcao): array
    {
        return match ($proporcao) {
            '4:5' => [1080, 1350],
            '9:16' => [1080, 1920],
            default => [1080, 1080],
        };
    }

    private function esc(string $texto): string
    {
        return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** Formata número para atributo SVG (sem casas decimais supérfluas). */
    private function n(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
