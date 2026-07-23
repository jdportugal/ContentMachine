<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Publicacoes\Dto\SlidePlano;
use Illuminate\Support\Str;

/**
 * Compõe o prompt de texto enviado ao kie.ai para cada cartão. Vive num serviço
 * partilhado para que a oficina consiga MOSTRAR e EDITAR o prompt exacto que o
 * renderizador envia — a mesma composição é usada nos dois lados.
 *
 * Três preocupações que este compositor resolve:
 *  - Coerência do carrossel: injecta o título da peça e os títulos dos cartões
 *    anteriores como CONTEXTO (para o modelo manter a mesma linha visual).
 *  - Anexos: descreve as imagens de referência atribuídas a este cartão, para o
 *    texto «fazer sentido» junto delas (as imagens em si vão como image_input).
 *  - Sem numeração no cartão: a posição (N de M) é apenas contexto e NUNCA deve
 *    ser desenhada — evita o artefacto «3 out of 7» impresso na imagem.
 */
class KiePromptComposer
{
    public function __construct(private readonly DesignSystemRepository $design) {}

    /**
     * Prompt de composição de raiz para um cartão.
     *
     * @param  array{
     *   proporcao?:string, capa?:bool, ordem?:int, total?:int,
     *   postTitulo?:string, anteriores?:array<int,string>, anexos?:array<int,string>
     * }  $ctx
     */
    public function paraCartao(SlidePlano $s, array $ctx = []): string
    {
        $proporcao = (string) ($ctx['proporcao'] ?? '1:1');
        $capa = (bool) ($ctx['capa'] ?? false);
        $ordem = (int) ($ctx['ordem'] ?? 1);
        $total = (int) ($ctx['total'] ?? 1);
        $anteriores = array_values(array_filter($ctx['anteriores'] ?? [], fn ($t) => trim((string) $t) !== ''));
        $anexos = array_values(array_filter($ctx['anexos'] ?? [], fn ($d) => trim((string) $d) !== ''));

        $papel = $capa
            ? 'É a CAPA do carrossel: título grande e central, muito legível.'
            : 'É um cartão de CONTEÚDO: título no topo e o texto por baixo, com respiração.';

        // A posição é CONTEXTO — nunca texto a desenhar.
        $contexto = $total > 1
            ? "CONTEXTO (não desenhar): este é o cartão {$ordem} de {$total} de um carrossel coeso"
                .($ctx['postTitulo'] ?? '' ? " intitulado «{$ctx['postTitulo']}»" : '').'.'
            : '';
        if ($anteriores !== []) {
            $contexto .= "\nCartões anteriores (para manteres a MESMA identidade visual, paleta e composição): "
                .implode(' · ', array_map(fn ($t) => '«'.$t.'»', $anteriores)).'.';
        }

        $blocoAnexos = $anexos !== []
            ? "\n\nImagens de referência anexas a este cartão (usa-as como guia visual e integra-as com coerência): "
                .implode('; ', $anexos).'.'
            : '';

        $texto = $s->texto !== '' ? 'TEXTO DE APOIO (exacto): «'.$s->texto.'»' : '';
        $estilo = $this->estiloMarca();

        return trim(<<<PROMPT
        Cartão para redes sociais. Proporção {$proporcao}. {$papel}
        {$contexto}

        {$estilo}{$blocoAnexos}

        Regras invariáveis: SEM logótipos de marcas terceiras, SEM emojis, SEM marcas de água,
        SEM numeração de página nem contadores (não escrevas «{$ordem}/{$total}», «{$ordem} de {$total}» nem nada do género no cartão).
        Compõe o seguinte texto, escrito EXACTAMENTE assim, em português europeu:
        TÍTULO: «{$s->titulo}»
        {$texto}

        Regra de texto: renderiza TODO o texto como letras reais, nítidas, bem espaçadas e SEM erros
        ortográficos. Não inventes, traduzas nem alteres palavras. O texto é o elemento central do cartão.
        PROMPT);
    }

    /** Prompt de edição imagem→imagem: parte da imagem dada e aplica só a mudança. */
    public function paraEdicao(SlidePlano $s, string $instrucao): string
    {
        return trim(<<<PROMPT
        Edita a imagem fornecida deste cartão, mantendo a MESMA identidade visual da marca, a mesma
        composição e o mesmo texto legível e sem erros.
        Aplica APENAS esta alteração pedida: «{$instrucao}».
        Mantém o título «{$s->titulo}» correcto e legível. Sem emojis, sem logótipos de terceiros,
        sem numeração de página.
        PROMPT);
    }

    /**
     * Diretriz de estilo a partir do Sistema de Design (design.md + tokens). Sem
     * sistema configurado, cai na estética por omissão.
     */
    public function estiloMarca(): string
    {
        $md = trim($this->design->read());
        $tokens = $this->design->readTokens();

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
