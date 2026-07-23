<?php

namespace App\Services\DesignSystem;

use App\Services\Clips\Api\RunsClaudeCli;
use Throwable;

/**
 * Turns the freeform design-system Markdown into a structured theme (colours,
 * fonts, texture) that the Remotion renderer consumes. Uses the authenticated
 * `claude` CLI. Any failure (no CLI, bad JSON, timeout) falls back to the IATECA
 * defaults so a render is never blocked.
 */
class DesignThemeExtractor
{
    use RunsClaudeCli;

    /**
     * @return array<string,mixed> a sanitized DesignTheme
     */
    public function extract(string $markdown): array
    {
        if (trim($markdown) === '') {
            return DesignTheme::defaults();
        }

        try {
            $envelope = $this->runClaude(
                $this->userPrompt($markdown),
                $this->systemPrompt(),
                ['maxTurns' => 1, 'timeout' => 120],
            );

            $json = $this->decodeJson((string) ($envelope['result'] ?? ''));

            return is_array($json) ? DesignTheme::sanitize($json) : DesignTheme::defaults();
        } catch (Throwable) {
            return DesignTheme::defaults();
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        És um sistema que converte um guia de design (Markdown) num conjunto de tokens
        para um motor de renderização de vídeo. Devolves SEMPRE e APENAS um objeto JSON
        válido (sem markdown, sem comentários, sem texto à volta), nesta forma EXACTA:

        {
          "colors": {
            "bg": "#rrggbb",            // fundo principal
            "bgAlt": "#rrggbb",         // superfície alternativa (cartões)
            "bgContrast": "#rrggbb",    // fundo de contraste (o mais escuro/forte)
            "textOnBg": "#rrggbb",      // texto sobre bg/bgAlt (TEM de contrastar)
            "textOnContrast": "#rrggbb",// texto sobre bgContrast (TEM de contrastar)
            "mutedOnBg": "#rrggbb",     // texto secundário sobre bg
            "mutedOnContrast": "#rrggbb",
            "accent": "#rrggbb",        // cor de destaque principal
            "accent2": "#rrggbb",       // destaque secundário
            "accent3": "#rrggbb"        // terceiro destaque/ornamento
          },
          "fonts": {
            "display": "Nome de família Google Fonts",  // títulos
            "body": "Nome de família Google Fonts",     // corpo
            "mono": "Nome de família Google Fonts"       // monoespaçada
          },
          "texture": { "kind": "paper" | "starfield" | "gradient" | "solid" }
        }

        REGRAS:
        - Extrai as cores REAIS do design (paleta, fundos, destaques). Usa hex #rrggbb.
        - Garante CONTRASTE: se o fundo for escuro, o texto tem de ser claro (e vice-versa).
        - As fontes TÊM de ser nomes reais do Google Fonts (ex.: "Anton", "Inter",
          "Montserrat", "Playfair Display"). Se o design indicar uma fonte não-Google,
          escolhe a alternativa Google Fonts mais próxima.
        - texture.kind: "starfield" para temas espaciais/escuros com estrelas; "gradient"
          para degradés; "solid" para fundo liso; "paper" para papel/textura orgânica.
        - Responde SÓ com o JSON.
        PROMPT;
    }

    private function userPrompt(string $markdown): string
    {
        return "Guia de design (Markdown):\n\n".$markdown."\n\nDevolve o JSON de tokens.";
    }

    /** Strip code fences and decode the first JSON object found. */
    private function decodeJson(string $text): mixed
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        return json_decode($text, true);
    }
}
