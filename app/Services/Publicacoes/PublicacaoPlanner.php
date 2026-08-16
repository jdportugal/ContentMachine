<?php

namespace App\Services\Publicacoes;

use App\Services\Aggregation\LlmClient;
use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use Illuminate\Support\Str;

/**
 * Plans a post from a free-form "brief". Asks the AI (LlmClient,
 * provider chain) for a plan in strict JSON; if the AI is not
 * available or the JSON is invalid, falls back to a deterministic heuristic.
 * Mirrors AdsMaker's PlanPostJob, adapted to the vault and working offline.
 */
class PublicacaoPlanner
{
    /** Source of the last plan: 'ia' (LLM) or 'heuristica' (local fallback). */
    public ?string $fonte = null;

    /** LLM provider used when $fonte === 'ia' (e.g. 'claude-cli'). */
    public ?string $fornecedor = null;

    private readonly LlmClient $llm;

    public function __construct(
        LlmClient $llm,
        private readonly PublicacaoKinds $kinds,
    ) {
        $this->llm = $llm->paraPasso('publicacoes_plano');
    }

    /** @param array<int,string> $referencias descriptions of the reference images */
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

    // ------------------------------------------------------------------ AI

    private function prompt(string $tipo, array $kind, string $brief, string $plataforma, array $referencias = []): string
    {
        $formato = $kind['formato'] ?? 'single';
        $c = $this->kinds->cartoes($tipo);
        $orientacao = (string) ($kind['plano_prompt'] ?? '');

        $regraCartoes = $formato === 'carousel'
            ? "Generate between {$c['min']} and {$c['max']} cards (the first is the cover)."
            : 'Generate exactly 1 card.';

        $blocoRefs = '';
        $temRefs = false;
        if ($referencias !== []) {
            // Accepts plain descriptions (['logótipo', …]) or indexed items
            // (['indice' => 0, 'descricao' => 'logótipo']). Shows the index so the
            // AI can ASSIGN each image to the cards where it makes sense.
            $linhas = [];
            foreach (array_values($referencias) as $pos => $ref) {
                if (is_array($ref)) {
                    $i = (int) ($ref['indice'] ?? $pos);
                    $d = trim((string) ($ref['descricao'] ?? ''));
                } else {
                    $i = $pos;
                    $d = trim((string) $ref);
                }
                $linhas[] = "[{$i}] ".($d !== '' ? $d : '(no description)');
            }
            if ($linhas !== []) {
                $temRefs = true;
                $lista = implode("\n", $linhas);
                $blocoRefs = "\n\nAvailable reference images (take them into account when writing; the text should make sense alongside them). "
                    ."Use the INDEX in square brackets to ASSIGN the relevant images to each card:\n{$lista}";
            }
        }

        $campoRefs = $temRefs
            ? ', "referencias": [0]  // indices of the images above that this card uses (may be [])'
            : '';
        $regraCoerencia = $formato === 'carousel'
            ? "\n        Coherence: the cards form ONE piece — chain them (cover → development → conclusion), without repeating, with a clear thread."
            : '';

        $design = app(\App\Services\DesignSystem\DesignSystemRepository::class)->read();
        $blocoDesign = trim($design) !== ''
            ? "\n\n=== DESIGN SYSTEM (brand identity — respect voice, tone and rules) ===\n{$design}\n"
            : '';

        return <<<PROMPT
        You are the writer of Brand Machine, a library for the age of thinking machines.
        Write in European Portuguese, sober and cultured tone, NO emojis.{$blocoDesign}

        Compose a piece of type «{$kind['label']}» for {$plataforma}.
        Format guidance: {$orientacao}
        {$regraCartoes}{$regraCoerencia}

        User theme / brief:
        {$brief}{$blocoRefs}

        Respond ONLY with valid JSON (no surrounding text), in this exact shape:
        {
          "titulo": "string",
          "legenda": "string (the caption for the post)",
          "tags": ["string"],
          "slides": [ {"ordem": 1, "titulo": "short string", "texto": "1 to 2 sentences"{$campoRefs}} ]
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

    /** Removes code fences (```json … ```) and trims to the first JSON object. */
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

    // ----------------------------------------------------------- heuristic

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

        // Cover + one card per brief sentence, with a label derived from the
        // sentence itself (not «Ponto N»). No invented text — the body is the original sentence.
        $partes = $this->frases($brief);
        $slides = [new SlidePlano(1, $titulo, '')];

        foreach ($partes as $frase) {
            $slides[] = new SlidePlano(count($slides) + 1, $this->rotulo($frase), $frase);
        }

        // Ensures the type's minimum by repeating/expanding what exists.
        while (count($slides) < $c['min']) {
            $slides[] = new SlidePlano(count($slides) + 1, 'Em síntese', $titulo.'.');
        }
        $slides = array_slice($slides, 0, $c['max']);
        foreach ($slides as $i => $s) {
            $s->ordem = $i + 1;
        }

        return new PublicacaoPlan($titulo, $brief, $tags, $slides);
    }

    /** Clean title from the brief: first sentence, without cutting mid-word. */
    private function tituloDe(string $brief): string
    {
        $frase = $this->primeiraFrase($brief);
        // Cut before a colon/dash (lists) so the enumeration is not dragged along.
        $frase = trim(preg_split('/\s*[:—–-]\s+/u', $frase)[0] ?? $frase);

        return Str::limit($frase, 70, '');
    }

    /** Short label (2–4 words) derived from the sentence, capitalized. */
    private function rotulo(string $frase): string
    {
        $palavras = preg_split('/\s+/u', trim($frase)) ?: [];
        $rotulo = trim(implode(' ', array_slice($palavras, 0, 4)), " .,;:—–-");

        return $rotulo !== '' ? Str::ucfirst($rotulo) : 'Ponto';
    }

    /** Splits the brief into non-empty sentences (by punctuation or lines). */
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

    /** Applies the type's card limits to a plan coming from the AI. */
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
