<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Support\DriverNotConfiguredException;

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

        return trim(<<<PROMPT
        Cartão para redes sociais da IATECA — uma biblioteca para a era das máquinas que pensam.
        Estética: página de livro antigo / gravura. Fundo de pergaminho creme, tinta castanho-escura,
        filete e ornamentos discretos a ouro velho, tipografia serifada elegante e centrada.
        Sóbrio e culto. SEM pessoas, SEM logótipos de marcas, SEM emojis, SEM molduras de fotografia.
        Proporção {$proporcao}. {$papel}

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
        Edita a imagem fornecida deste cartão da IATECA, mantendo a mesma estética de livro antigo
        (pergaminho, tinta, ouro), a mesma composição e o mesmo texto legível e sem erros.
        Aplica APENAS esta alteração pedida: «{$instrucao}».
        Mantém o título «{$s->titulo}» correcto e legível. Sem emojis, sem logótipos, sem pessoas.
        PROMPT);
    }
}
