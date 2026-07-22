<?php

namespace App\Services\Publicacoes;

use App\Services\Aggregation\LlmClient;
use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use Illuminate\Support\Str;

/**
 * Planeia uma publicação a partir de um "brief" livre. Pede à IA (LlmClient,
 * cadeia de fornecedores) um plano em JSON estrito; se a IA não estiver
 * disponível ou o JSON for inválido, cai numa heurística determinística.
 * Espelha o PlanPostJob do AdsMaker, adaptado ao vault e a funcionar offline.
 */
class PublicacaoPlanner
{
    /** Fonte do último plano: 'ia' (LLM) ou 'heuristica' (fallback local). */
    public ?string $fonte = null;

    /** Fornecedor de LLM usado quando $fonte === 'ia' (ex.: 'claude-cli'). */
    public ?string $fornecedor = null;

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PublicacaoKinds $kinds,
    ) {}

    /** @param array<int,string> $referencias descrições das imagens de referência */
    public function planear(string $tipo, string $brief, string $plataforma, array $referencias = []): PublicacaoPlan
    {
        $kind = $this->kinds->get($tipo) ?? [];
        $brief = trim($brief);
        $this->fonte = null;
        $this->fornecedor = null;

        if ($brief !== '') {
            $texto = $this->llm->texto($this->prompt($tipo, $kind, $brief, $plataforma, $referencias));
            $plano = $this->deJson($texto);

            if ($plano instanceof PublicacaoPlan && $plano->slides !== []) {
                $this->fonte = 'ia';
                $this->fornecedor = $this->llm->fornecedorAtivo();

                return $this->normalizarCartoes($plano, $tipo);
            }
        }

        $this->fonte = 'heuristica';

        return $this->heuristica($tipo, $kind, $brief, $plataforma);
    }

    // ------------------------------------------------------------------ IA

    private function prompt(string $tipo, array $kind, string $brief, string $plataforma, array $referencias = []): string
    {
        $formato = $kind['formato'] ?? 'single';
        $c = $this->kinds->cartoes($tipo);
        $orientacao = (string) ($kind['plano_prompt'] ?? '');

        $regraCartoes = $formato === 'carousel'
            ? "Gera entre {$c['min']} e {$c['max']} cartões (o primeiro é a capa)."
            : 'Gera exactamente 1 cartão.';

        $blocoRefs = '';
        if ($referencias !== []) {
            $lista = implode("\n", array_map(fn ($d) => '- '.$d, $referencias));
            $blocoRefs = "\n\nImagens de referência que acompanham esta peça (tem-nas em conta ao redigir; o texto deve fazer sentido junto delas):\n{$lista}";
        }

        $design = app(\App\Services\DesignSystem\DesignSystemRepository::class)->read();
        $blocoDesign = trim($design) !== ''
            ? "\n\n=== SISTEMA DE DESIGN (identidade da marca — respeita voz, tom e regras) ===\n{$design}\n"
            : '';

        return <<<PROMPT
        És o redator da IATECA, uma biblioteca para a era das máquinas que pensam.
        Escreve em português europeu, tom sóbrio e culto, SEM emojis.{$blocoDesign}

        Compõe uma peça do tipo «{$kind['label']}» para {$plataforma}.
        Orientação do formato: {$orientacao}
        {$regraCartoes}

        Tema / brief do utilizador:
        {$brief}{$blocoRefs}

        Responde APENAS com JSON válido (sem texto à volta), nesta forma exacta:
        {
          "titulo": "string",
          "legenda": "string (a legenda/caption para a publicação)",
          "tags": ["string"],
          "slides": [ {"ordem": 1, "titulo": "string curto", "texto": "1 a 2 frases"} ]
        }
        PROMPT;
    }

    private function deJson(?string $texto): ?PublicacaoPlan
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }

        $limpo = $this->stripFences($texto);
        $dados = json_decode($limpo, true);

        if (! is_array($dados)) {
            return null;
        }

        return PublicacaoPlan::fromArray($dados);
    }

    /** Remove cercas de código (```json … ```) e recorta ao primeiro objecto JSON. */
    private function stripFences(string $texto): string
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```(?:json)?\s*/i', '', $texto);
        $texto = preg_replace('/\s*```$/', '', (string) $texto);

        $ini = strpos((string) $texto, '{');
        $fim = strrpos((string) $texto, '}');

        if ($ini !== false && $fim !== false && $fim >= $ini) {
            return substr((string) $texto, $ini, $fim - $ini + 1);
        }

        return (string) $texto;
    }

    // ----------------------------------------------------------- heurística

    private function heuristica(string $tipo, array $kind, string $brief, string $plataforma): PublicacaoPlan
    {
        $formato = $kind['formato'] ?? 'single';
        $c = $this->kinds->cartoes($tipo);
        $brief = $brief !== '' ? $brief : 'Nova peça';

        $titulo = $this->tituloDe($brief);
        $tags = array_values(array_filter([$tipo, $plataforma]));

        if ($formato === 'single') {
            return new PublicacaoPlan(
                titulo: $titulo,
                legenda: $brief,
                tags: $tags,
                slides: [new SlidePlano(1, $titulo, $brief)],
            );
        }

        // Capa + um cartão por frase do brief, com um rótulo derivado da própria
        // frase (não «Ponto N»). Sem texto inventado — o corpo é a frase original.
        $partes = $this->frases($brief);
        $slides = [new SlidePlano(1, $titulo, '')];

        foreach ($partes as $frase) {
            $slides[] = new SlidePlano(count($slides) + 1, $this->rotulo($frase), $frase);
        }

        // Garante o mínimo do tipo repetindo/desdobrando o que existe.
        while (count($slides) < $c['min']) {
            $slides[] = new SlidePlano(count($slides) + 1, 'Em síntese', $titulo.'.');
        }
        $slides = array_slice($slides, 0, $c['max']);
        foreach ($slides as $i => $s) {
            $s->ordem = $i + 1;
        }

        return new PublicacaoPlan($titulo, $brief, $tags, $slides);
    }

    /** Título limpo a partir do brief: primeira frase, sem cortar a meio de palavra. */
    private function tituloDe(string $brief): string
    {
        $frase = $this->primeiraFrase($brief);
        // Corta antes de dois-pontos/travessão (listas) para não arrastar a enumeração.
        $frase = trim(preg_split('/\s*[:—–-]\s+/u', $frase)[0] ?? $frase);

        return Str::limit($frase, 70, '');
    }

    /** Rótulo curto (2–4 palavras) derivado da frase, em maiúscula inicial. */
    private function rotulo(string $frase): string
    {
        $palavras = preg_split('/\s+/u', trim($frase)) ?: [];
        $rotulo = trim(implode(' ', array_slice($palavras, 0, 4)), " .,;:—–-");

        return $rotulo !== '' ? Str::ucfirst($rotulo) : 'Ponto';
    }

    /** Divide o brief em frases não vazias (por pontuação ou linhas). */
    private function frases(string $texto): array
    {
        $pedacos = preg_split('/(?<=[.!?])\s+|\R+/u', trim($texto)) ?: [];

        return array_values(array_filter(array_map('trim', $pedacos), fn ($f) => $f !== ''));
    }

    private function primeiraFrase(string $texto): string
    {
        $frases = $this->frases($texto);

        return $frases[0] ?? trim($texto);
    }

    /** Aplica os limites de cartões do tipo a um plano vindo da IA. */
    private function normalizarCartoes(PublicacaoPlan $plano, string $tipo): PublicacaoPlan
    {
        $formato = $this->kinds->formato($tipo);
        $c = $this->kinds->cartoes($tipo);

        if ($formato === 'single') {
            $plano->slides = array_slice($plano->slides, 0, 1);

            return $plano;
        }

        $plano->slides = array_slice($plano->slides, 0, $c['max']);
        foreach ($plano->slides as $i => $s) {
            $s->ordem = $i + 1;
        }

        return $plano;
    }
}
