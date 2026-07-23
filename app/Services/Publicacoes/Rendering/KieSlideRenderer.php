<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Support\DriverNotConfiguredException;

/**
 * Driver de produção: gera cada cartão com a API kie.ai (nano-banana-pro),
 * à imagem do AdsMaker. Devolve URLs de imagem.
 *
 * O prompt de texto de cada cartão vem do KiePromptComposer (partilhado com a
 * oficina, que o mostra/edita). Se a oficina enviar um prompt já composto/editado
 * (kind['_prompts'][i]), esse é usado tal e qual; caso contrário compõe-se aqui.
 *
 * Coerência do carrossel: cada cartão recebe como referência visual (image_input)
 * as referências globais + os anexos próprios + TODAS as páginas já geradas antes
 * dele — para manter a mesma identidade ao longo da peça.
 *
 * Requer KIE_API_KEY. Sem chave, a app usa o SvgSlideRenderer (ver
 * AppServiceProvider), por isso este driver não é exercido offline.
 */
class KieSlideRenderer implements SlideRenderer
{
    /** kie.ai aceita no máximo 8 imagens de referência (image_input) por pedido. */
    private const MAX_IMAGE_INPUT = 8;

    public function __construct(
        private readonly KieClient $kie,
        private readonly KiePromptComposer $composer,
    ) {}

    public function render(PublicacaoPlan $plan, array $kind): array
    {
        $this->exigirChave();

        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $total = count($plan->slides);

        $baseRefs = $this->kie->carregarReferencias($kind['_refs'] ?? []);
        $anexosPaths = (array) ($kind['_anexos'] ?? []);   // [i => [caminho, …]]
        $anexosDescr = (array) ($kind['_anexosDescr'] ?? []); // [i => [descrição, …]]
        $prompts = (array) ($kind['_prompts'] ?? []);      // [i => prompt editado]

        $urls = [];
        $anteriores = [];        // URLs kie de páginas já geradas (coerência visual)
        $anterioresTitulos = []; // títulos anteriores (coerência textual no prompt)

        foreach ($plan->slides as $i => $slide) {
            $anexoUrls = $this->kie->carregarReferencias($anexosPaths[$i] ?? []);

            $prompt = trim((string) ($prompts[$i] ?? '')) !== ''
                ? (string) $prompts[$i]
                : $this->composer->paraCartao($slide, [
                    'proporcao' => $proporcao,
                    'capa' => $i === 0,
                    'ordem' => $i + 1,
                    'total' => $total,
                    'postTitulo' => $plan->titulo,
                    'anteriores' => $anterioresTitulos,
                    'anexos' => array_values((array) ($anexosDescr[$i] ?? [])),
                ]);

            $url = $this->kie->generate(
                $prompt,
                $proporcao,
                $this->limitarInput($anexoUrls, $baseRefs, $anteriores),
            );

            $urls[] = $url;
            $anteriores[] = $url;
            $anterioresTitulos[] = $slide->titulo;
        }

        return $urls;
    }

    public function renderCartao(SlidePlano $slide, array $kind, ?string $refImagem = null, string $instrucao = '', int $ordem = 1, int $total = 1): string
    {
        $this->exigirChave();

        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $baseRefs = $this->kie->carregarReferencias($kind['_refs'] ?? []);
        $anexoUrls = $this->kie->carregarReferencias((array) ($kind['_anexos'] ?? []));

        // Edição imagem→imagem: mantém o cartão e aplica a instrução (+ referências).
        if ($refImagem !== null && trim($instrucao) !== '') {
            $url = $this->kie->upload($refImagem, 'atual.png');

            return $this->kie->generate(
                $this->composer->paraEdicao($slide, $instrucao),
                $proporcao,
                // A imagem actual (a editar) é a prioridade máxima.
                $this->limitarInput([$url], array_merge($anexoUrls, $baseRefs), []),
            );
        }

        // Composição de novo: usa o prompt enviado pela oficina, ou compõe-o.
        $prompt = trim((string) ($kind['_prompt'] ?? '')) !== ''
            ? (string) $kind['_prompt']
            : $this->composer->paraCartao($slide, [
                'proporcao' => $proporcao,
                'capa' => $ordem === 1,
                'ordem' => $ordem,
                'total' => $total,
                'anexos' => array_values((array) ($kind['_anexosDescr'] ?? [])),
            ]);

        return $this->kie->generate($prompt, $proporcao, $this->limitarInput($anexoUrls, $baseRefs, []));
    }

    /**
     * Limita o image_input ao máximo do kie.ai (8), por ordem de prioridade:
     * anexos do cartão → capa/âncora de identidade → referências globais →
     * páginas anteriores mais recentes. Assim a coerência mantém-se (capa + as
     * páginas mais próximas) sem exceder o limite da API.
     *
     * @param  array<int,string>  $anexos     imagens específicas do cartão
     * @param  array<int,string>  $globais    referências aplicadas a toda a peça
     * @param  array<int,string>  $anteriores páginas já geradas (0 = capa)
     * @return array<int,string>
     */
    private function limitarInput(array $anexos, array $globais, array $anteriores): array
    {
        $capa = $anteriores !== [] ? [$anteriores[0]] : [];
        $recentes = array_reverse(array_slice($anteriores, 1)); // mais recente primeiro

        $ordenado = array_merge($anexos, $capa, $globais, $recentes);

        return array_slice(array_values(array_unique($ordenado)), 0, self::MAX_IMAGE_INPUT);
    }

    private function exigirChave(): void
    {
        if (! $this->kie->configurado()) {
            throw DriverNotConfiguredException::for('kie', 'KIE_API_KEY');
        }
    }
}
