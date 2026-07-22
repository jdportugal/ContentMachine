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
    public function __construct(
        private readonly LlmClient $llm,
        private readonly PublicacaoKinds $kinds,
    ) {}

    public function planear(string $tipo, string $brief, string $plataforma): PublicacaoPlan
    {
        $kind = $this->kinds->get($tipo) ?? [];
        $brief = trim($brief);

        if ($brief !== '') {
            $texto = $this->llm->texto($this->prompt($tipo, $kind, $brief, $plataforma));
            $plano = $this->deJson($texto);

            if ($plano instanceof PublicacaoPlan && $plano->slides !== []) {
                return $this->normalizarCartoes($plano, $tipo);
            }
        }

        return $this->heuristica($tipo, $kind, $brief, $plataforma);
    }

    // ------------------------------------------------------------------ IA

    private function prompt(string $tipo, array $kind, string $brief, string $plataforma): string
    {
        $formato = $kind['formato'] ?? 'single';
        $c = $this->kinds->cartoes($tipo);
        $orientacao = (string) ($kind['plano_prompt'] ?? '');

        $regraCartoes = $formato === 'carousel'
            ? "Gera entre {$c['min']} e {$c['max']} cartões (o primeiro é a capa)."
            : 'Gera exactamente 1 cartão.';

        return <<<PROMPT
        És o redator da IATECA, uma biblioteca para a era das máquinas que pensam.
        Escreve em português europeu, tom sóbrio e culto, SEM emojis.

        Compõe uma peça do tipo «{$kind['label']}» para {$plataforma}.
        Orientação do formato: {$orientacao}
        {$regraCartoes}

        Tema / brief do utilizador:
        {$brief}

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

        $titulo = Str::limit($this->primeiraFrase($brief), 70, '');
        $tags = array_values(array_filter([$tipo, $plataforma]));

        if ($formato === 'single') {
            return new PublicacaoPlan(
                titulo: $titulo,
                legenda: $brief,
                tags: $tags,
                slides: [new SlidePlano(1, $titulo, $brief)],
            );
        }

        $partes = $this->frases($brief);
        $slides = [new SlidePlano(1, $titulo, 'Um fio sobre '.Str::lower($titulo).'.')];

        foreach ($partes as $i => $frase) {
            $slides[] = new SlidePlano(count($slides) + 1, 'Ponto '.($i + 1), $frase);
        }

        // Garante o mínimo do tipo; recorta ao máximo.
        while (count($slides) < $c['min']) {
            $slides[] = new SlidePlano(count($slides) + 1, 'Ponto '.count($slides), 'A desenvolver.');
        }
        $slides = array_slice($slides, 0, $c['max']);
        foreach ($slides as $i => $s) {
            $s->ordem = $i + 1;
        }

        return new PublicacaoPlan($titulo, $brief, $tags, $slides);
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
