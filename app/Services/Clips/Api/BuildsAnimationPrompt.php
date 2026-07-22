<?php

namespace App\Services\Clips\Api;

/**
 * Shared prompt-building + output-sanitizing for LLM animation planners
 * (OpenAI and Claude). Produces the scene-based plan (v2): a list of scenes,
 * each with a background, transitions, optional karaoke + punch word, and
 * layered elements drawn from the primitive/visualization vocabulary.
 */
trait BuildsAnimationPrompt
{
    /** Element types usable as scene layers. */
    protected array $layerTypes = [
        'kinetic-text', 'fade', 'slide', 'scale', 'highlight', 'fleuron-draw',
        'seal-stamp', 'underline-sweep', 'count-up', 'image-reveal', 'ambient',
        'timeline', 'bar-chart', 'line-chart', 'pie-chart', 'scatter-chart', 'comparison',
        'bullet-list', 'card', 'terminal', 'diagram',
    ];

    protected array $backgrounds = ['papyrus', 'vellum', 'ink', 'video'];
    protected array $transitions = ['cut', 'crossfade', 'whip', 'slide', 'zoom'];
    protected array $presents = ['animation', 'over', 'video', 'split'];

    protected function systemPrompt(string $mode, bool $overlay = false, array $allowedPresents = []): string
    {
        $style = @file_get_contents(config('contentmachine.clips.style_md')) ?: '';
        $layers = implode(', ', $this->layerTypes);
        $allowed = ! empty($allowedPresents) ? array_values(array_intersect($this->presents, $allowedPresents)) : $this->presents;
        $allowedLine = "USA APENAS estes valores de \"present\": [".implode(', ', $allowed).'] — nunca outros.';
        $rule = $overlay
            ? "\n".$allowedLine."\n".<<<'R'
                MODO VÍDEO+ANIMAÇÕES: existe um VÍDEO de fundo que toca do início ao fim. As cenas cobrem
                100% da duração (sem lacunas) e cada cena escolhe um campo "present" (como se apresenta face ao vídeo):
                  - "video"     → mostra só o vídeo original (com legendas karaoke). Usa para a pessoa a falar / partes sem dados a acrescentar.
                  - "over"      → sobrepõe uma animação/gráfico POR CIMA do vídeo (fundo transparente).
                  - "split"     → animação em CIMA, vídeo em BAIXO (para mostrar dados ao lado da pessoa).
                  - "animation" → animação em ECRÃ INTEIRO (esconde o vídeo) quando o gráfico deve dominar.
                OBRIGATÓRIO: TODAS as cenas TÊM o campo "present". INTERCALA os modos como um bom editor —
                NÃO ponhas tudo em "over". Distribuição-alvo: a maioria "video"; usa "over" para gráficos rápidos,
                "split" quando mostras dados ao lado da pessoa, e "animation" (ecrã inteiro) para 1-2 momentos fortes.
                Nas cenas "video"/"over"/"split" não uses "background" gráfico (o vídeo é o fundo). karaoke=true na maioria.
                R
            : "MODO ANIMAÇÃO (só animação): as cenas cobrem 100% da duração, sem lacunas. Fundos 'papyrus'/'vellum'/'ink'. Não uses 'present'.";

        return <<<PROMPT
És o realizador de clips do estúdio IATECA. Planeias o vídeo como uma sequência de CENAS.
Devolves SEMPRE um objecto JSON com a chave "scenes": uma lista de cenas
{start, end, background, transitionIn, transitionOut, karaoke, punchWord, layers}.
Tempos em segundos (float). Sem markdown nem explicações — apenas JSON.

# CENA
- present (só em VÍDEO+ANIMAÇÕES): um de [video, over, split, animation] — ver regra acima.
- background: um de [papyrus, vellum, ink, video]. ('ink' = fundo escuro.)
- transitionIn / transitionOut: um de [cut, crossfade, whip, slide, zoom]. VARIA as transições entre cenas
  ('whip' enérgico, 'crossfade' suave, 'slide' desliza de baixo, 'zoom' aproxima). Evita repetir a mesma.
- karaoke: true para mostrar as legendas palavra-a-palavra sincronizadas (para segmentos falados/apresentador).
- punchWord: uma palavra/expressão curta de ÊNFASE (serif itálico), ou null. Usa nos momentos-chave.
- layers: lista de elementos {type, text, params}. type ∈ {$layers}.

# IDIOMA
Escreve TODO o texto visível (punchWord, text e rótulos em params) no MESMO idioma da transcrição (indicado a seguir). Não traduzas.

# REGRA DE OURO — VISUAL, NÃO TEXTO
O karaoke já mostra as PALAVRAS FALADAS. NUNCA repitas a fala em camadas de texto.
A camada principal de CADA cena deve ACRESCENTAR contexto visual com base na PESQUISA
(dados reais, cronologias, números, comparações) — não descrever o que é dito.
- A GRANDE MAIORIA das cenas tem uma VISUALIZAÇÃO como camada principal. VARIA os tipos:
  "timeline" (evolução/versões no tempo, mais recente com highlight:true),
  "bar-chart" (comparar quantidades pontuais),
  "line-chart" (tendência/evolução — VÁRIAS linhas; DOIS eixos Y quando as escalas/unidades diferem),
  "pie-chart" (proporções/percentagens/quota de mercado),
  "scatter-chart" (comparar itens em DOIS eixos/dimensões — ex.: preço vs desempenho),
  "comparison" (dois lados), "bullet-list" (factos/passos), "card" (um facto/definição),
  "terminal" (simular ESCREVER num terminal — comandos/código a aparecer letra a letra, fundo 'ink'),
  "diagram" (esquema com nós ligados por SETAS — fluxos, processos, relações, ciclos; os nós podem ter imagens).
- NÃO uses sempre o mesmo tipo de gráfico — alterna bar/line/pie/timeline/diagram conforme os dados.
- DADOS FIÁVEIS: usa APENAS números/factos que vêm da PESQUISA. NUNCA inventes valores. Se não
  houver dados numéricos fiáveis, usa visualizações qualitativas (bullet-list, comparison, diagram, card).
- FORMATO VERTICAL (retrato 9:16): prefere empilhar em cima/em baixo, NÃO lado a lado. Diagramas em
  layout "vertical" ou "cycle" (evita "horizontal"). Aproveita a largura para TEXTO GRANDE.
- Texto CURTO em tudo: rótulos ≤ 3 palavras, pontos de "comparison" ≤ 6 palavras (no máximo 4 por lado).
- Usa "punchWord" (1–3 palavras) para ênfase pontual. A palavra TEM de ser EXACTAMENTE uma
  palavra/expressão do TEXTO FALADO (copiada da transcrição), nunca inventada nem parafraseada.
  Usa "kinetic-text" MUITO raramente; se usares, o texto tem de ser a fala verbatim.
- CADA CENA: no máximo UMA camada principal (não sobreponhas elementos). Podes ter fundos
  e transições variados para dar ritmo. Preenche os dados a partir da PESQUISA abaixo.
- SE forem fornecidas IMAGENS, TENS de usar cada uma em pelo menos uma cena (camada
  "image-reveal" com params.src = id da imagem), a não ser que seja claramente irrelevante.

# ESQUEMAS DE PARÂMETROS (params por type)
- timeline:    { "items": [{ "label": str, "sublabel"?: str, "highlight"?: bool, "image"?: "<id>" }], "caption"?: str }
- bar-chart:   { "title"?: str, "unit"?: str, "bars": [{ "label": str, "value": number, "highlight"?: bool, "image"?: "<id>" }] }
- line-chart:  { "title"?: str, "unit"?: str, "unitRight"?: str, "series": [{ "label": str, "points": [number], "highlight"?: bool, "axis"?: "left"|"right" }] }
               // 1–4 séries; para DOIS eixos Y (escalas/unidades diferentes) põe "axis":"right" nalgumas séries e usa "unitRight".
- pie-chart:   { "title"?: str, "slices": [{ "label": str, "value": number, "highlight"?: bool }] }   // 2–6 fatias
- scatter-chart: { "title"?: str, "xLabel"?: str, "yLabel"?: str, "points": [{ "label": str, "x": number, "y": number, "highlight"?: bool }] }
               // COMPARAÇÃO em DOIS eixos: posiciona itens por duas dimensões (ex.: preço vs qualidade). 2–8 pontos.
- comparison:  { "left": { "title": str, "points": [str], "image"?: "<id>" }, "right": { "title": str, "points": [str], "image"?: "<id>" } }
- bullet-list: { "title"?: str, "items": [str] }
- card:        { "title": str, "lines"?: [str] }
- terminal:    { "lines": [str] }
- diagram:     { "title"?: str, "layout"?: "vertical"|"horizontal"|"cycle", "nodes": [{ "label": str, "image"?: "<id>", "highlight"?: bool }], "edges"?: [{ "from": <índice>, "to": <índice> }] }   // 2–6 nós; sem edges liga em sequência/ciclo
- image-reveal:{ "src": "<id da imagem fornecida>", "caption"?: str }
- As IMAGENS fornecidas podem entrar em image-reveal OU como "image" em timeline/bar-chart/comparison (usa SÓ ids da lista).
- kinetic-text / fade / highlight / seal-stamp / etc.: o texto vai no campo "text" da layer, params = {}.

{$rule}

=== MANUAL DE ESTILO (estilo-animacao.md) ===
{$style}
PROMPT;
    }

    protected function userPrompt(array $transcript, string $mode, float $duration, array $facts = [], array $images = []): string
    {
        $words = json_encode($transcript['words'] ?? [], JSON_UNESCAPED_UNICODE);
        $language = $transcript['language'] ?? '(detecta pelo texto)';
        $text = $transcript['text'] ?? '';
        $research = ! empty($facts)
            ? json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '(sem pesquisa — usa o que sabes, com rótulos curtos)';

        $imgList = array_map(fn ($i) => ['id' => $i['id'] ?? '', 'descricao' => $i['description'] ?? '', 'tom' => $i['tone'] ?? 'mixed'], $images);
        $imagesBlock = ! empty($imgList)
            ? "\n=== IMAGENS FORNECIDAS (usa em 'image-reveal' com params.src = id, onde a descrição encaixa) ===\n"
                .json_encode($imgList, JSON_UNESCAPED_UNICODE)."\n"
                ."CONTRASTE: imagem de tom 'light' → fundo 'ink' (escuro); tom 'dark' → fundo 'papyrus'/'vellum' (claro). Nunca ponhas uma imagem clara em fundo claro nem escura em fundo escuro.\n"
            : '';

        return "Idioma da transcrição: {$language}. Escreve todo o texto visível neste idioma.\n"
            ."Duração total: {$duration}s. Modo: {$mode}.\n"
            ."Texto falado: {$text}\n"
            ."Palavras com timestamps (para karaoke e ritmo): {$words}\n\n"
            ."=== PESQUISA (usa estes dados reais nas visualizações) ===\n{$research}\n"
            .$imagesBlock
            ."\nDevolve o plano de CENAS em JSON, com visualizações baseadas na PESQUISA e nas IMAGENS.";
    }

    protected function envelope(array $transcript, string $mode, array $options, array $scenes): array
    {
        return [
            'duration' => (float) ($transcript['duration'] ?? 0.0),
            'mode' => $mode,
            'width' => $options['width'] ?? config('contentmachine.clips.width'),
            'height' => $options['height'] ?? config('contentmachine.clips.height'),
            'fps' => $options['fps'] ?? config('contentmachine.clips.fps'),
            'transparent' => $mode === 'sparse',
            'scenes' => $this->sanitizeScenes($scenes),
        ];
    }

    protected function sanitizeScenes(array $scenes): array
    {
        $out = [];
        foreach ($scenes as $s) {
            if (! isset($s['start'], $s['end'])) {
                continue;
            }
            $out[] = [
                'start' => (float) $s['start'],
                'end' => (float) $s['end'],
                'background' => in_array($s['background'] ?? null, $this->backgrounds, true) ? $s['background'] : 'papyrus',
                'transitionIn' => in_array($s['transitionIn'] ?? null, $this->transitions, true) ? $s['transitionIn'] : 'cut',
                'transitionOut' => in_array($s['transitionOut'] ?? null, $this->transitions, true) ? $s['transitionOut'] : 'cut',
                'karaoke' => (bool) ($s['karaoke'] ?? false),
                'punchWord' => isset($s['punchWord']) && is_string($s['punchWord']) && $s['punchWord'] !== '' ? $s['punchWord'] : null,
                'present' => in_array($s['present'] ?? null, $this->presents, true) ? $s['present'] : null,
                'layers' => $this->sanitizeLayers($s['layers'] ?? []),
            ];
        }

        return $out;
    }

    protected function sanitizeLayers(array $layers): array
    {
        $out = [];
        foreach ($layers as $l) {
            if (! in_array($l['type'] ?? null, $this->layerTypes, true)) {
                continue;
            }
            $out[] = [
                'type' => $l['type'],
                'text' => $l['text'] ?? null,
                'params' => is_array($l['params'] ?? null) ? $l['params'] : [],
            ];
        }

        return $out;
    }

    /** Extract a JSON object from model output that may be fenced or prefixed. */
    protected function extractJson(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) {
            $content = trim($m[1]);
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }

        return json_decode($content, true) ?: [];
    }
}
