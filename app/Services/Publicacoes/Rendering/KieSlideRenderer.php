<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Support\DriverNotConfiguredException;
use Illuminate\Support\Str;

/**
 * Driver de produção: gera cada cartão com a API kie.ai (nano-banana-pro),
 * à imagem do AdsMaker. Devolve URLs de imagem.
 *
 * Requer KIE_API_KEY. Sem chave, a app usa o SvgSlideRenderer (ver
 * AppServiceProvider), por isso este driver não é exercido offline.
 */
class KieSlideRenderer implements SlideRenderer
{
    public function __construct(private readonly KieClient $kie) {}

    public function render(PublicacaoPlan $plan, array $kind): array
    {
        $this->exigirChave();

        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $total = count($plan->slides);
        $refs = $this->kie->carregarReferencias($kind['_refs'] ?? []);
        $urls = [];
        $ancora = null; // 1.º cartão serve de referência aos seguintes (consistência visual).

        foreach ($plan->slides as $i => $slide) {
            $url = $this->kie->generate(
                $this->prompt($slide, $kind, $i === 0, $i + 1, $total),
                $proporcao,
                array_merge($refs, $ancora !== null ? [$ancora] : []),
            );
            $urls[] = $url;
            $ancora ??= $url;
        }

        return $urls;
    }

    public function renderCartao(SlidePlano $slide, array $kind, ?string $refImagem = null, string $instrucao = '', int $ordem = 1, int $total = 1): string
    {
        $this->exigirChave();
        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $refs = $this->kie->carregarReferencias($kind['_refs'] ?? []);

        // Edição imagem→imagem: mantém o cartão e aplica a instrução (+ referências).
        if ($refImagem !== null && trim($instrucao) !== '') {
            $url = $this->kie->upload($refImagem, 'atual.png');

            return $this->kie->generate($this->promptEdicao($slide, $instrucao), $proporcao, array_merge($refs, [$url]));
        }

        // Composição de novo a partir do texto, com as referências.
        return $this->kie->generate($this->prompt($slide, $kind, $ordem === 1, $ordem, $total), $proporcao, $refs);
    }

    private function exigirChave(): void
    {
        if (! $this->kie->configurado()) {
            throw DriverNotConfiguredException::for('kie', 'KIE_API_KEY');
        }
    }

    /**
     * Prompt para o modelo com texto (nano-banana-pro): cartão editorial da
     * IATECA com o texto EXACTO, legível e correctamente escrito.
     */
    private function prompt(SlidePlano $s, array $kind, bool $capa, int $ordem, int $total): string
    {
        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $papel = $capa
            ? 'É a CAPA do carrossel: título grande e central, muito legível.'
            : 'É um cartão de CONTEÚDO ('.$ordem.' de '.$total.'): título no topo e o texto por baixo, com respiração.';

        $texto = $s->texto !== '' ? 'TEXTO DE APOIO (exacto): «'.$s->texto.'»' : '';
        $estilo = $this->estiloMarca();

        return trim(<<<PROMPT
        Cartão para redes sociais. Proporção {$proporcao}. {$papel}

        {$estilo}

        Regras invariáveis: SEM logótipos de marcas terceiras, SEM emojis, SEM marcas de água.
        Compõe o seguinte texto, escrito EXACTAMENTE assim, em português europeu:
        TÍTULO: «{$s->titulo}»
        {$texto}

        Regra de texto: renderiza TODO o texto como letras reais, nítidas, bem espaçadas e SEM erros
        ortográficos. Não inventes, traduzas nem alteres palavras. O texto é o elemento central do cartão.
        PROMPT);
    }

    /** Prompt de edição: parte da imagem dada e aplica só a mudança pedida. */
    private function promptEdicao(SlidePlano $s, string $instrucao): string
    {
        return trim(<<<PROMPT
        Edita a imagem fornecida deste cartão, mantendo a MESMA identidade visual da marca, a mesma
        composição e o mesmo texto legível e sem erros.
        Aplica APENAS esta alteração pedida: «{$instrucao}».
        Mantém o título «{$s->titulo}» correcto e legível. Sem emojis, sem logótipos de terceiros.
        PROMPT);
    }

    /**
     * Diretriz de estilo para a imagem, a partir do Sistema de Design (design.md
     * + tokens). Sem sistema de design configurado, usa a estética IATECA.
     */
    private function estiloMarca(): string
    {
        $design = app(DesignSystemRepository::class);
        $md = trim($design->read());
        $tokens = $design->readTokens();

        if ($md === '' && ! $tokens) {
            return 'Estética: página de livro antigo / gravura. Fundo de pergaminho creme, tinta '
                .'castanho-escura, filete e ornamentos discretos a ouro velho, tipografia serifada '
                .'elegante e centrada. Sóbrio e culto, sem pessoas nem molduras de fotografia.';
        }

        $linhas = ['ESTILO DE MARCA — aplica RIGOROSAMENTE esta identidade visual ao cartão:'];

        if ($md !== '') {
            $linhas[] = Str::limit($md, 1600);
        }

        if ($tokens) {
            $c = $tokens['colors'] ?? [];
            $f = $tokens['fonts'] ?? [];
            $partes = array_filter([
                (isset($c['bg'], $c['textOnBg']) ? "Paleta: fundo {$c['bg']}, texto {$c['textOnBg']}" : '')
                    .(isset($c['accent']) ? ", destaque {$c['accent']}" : '').(isset($c['accent2']) ? " / {$c['accent2']}" : '').'.',
                isset($f['display'], $f['body']) ? "Tipografia: títulos tipo «{$f['display']}», corpo tipo «{$f['body']}»." : '',
                ! empty($tokens['texture']['kind']) ? "Fundo/textura: {$tokens['texture']['kind']}." : '',
            ]);
            if ($partes !== []) {
                $linhas[] = 'Tokens concretos — '.implode(' ', $partes);
            }
        }

        return implode("\n\n", $linhas);
    }
}
