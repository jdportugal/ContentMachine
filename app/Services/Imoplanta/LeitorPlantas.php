<?php

namespace App\Services\Imoplanta;

use App\Services\Clips\Api\RunsClaudeCli;
use RuntimeException;

/**
 * The two LLM-with-vision steps of Imoplanta, over the clip pipeline's provider
 * chain (Claude API / CLI → GPT; DeepSeek can't see images and is skipped by
 * the chain's own vision rule): read the plan images into a house description
 * (ImoplantaCasa), and apply a customer's free-text change to one. Every call's
 * tokens are recorded against the property (ImoplantaCustos).
 */
class LeitorPlantas
{
    use RunsClaudeCli;

    public function __construct(private ImoplantaCustos $custos) {}

    protected function passo(): string
    {
        return 'imoplanta_modelo';
    }

    /**
     * @param  list<string>  $imagens  absolute paths of the plan images
     * @param  string  $contexto  what kind of home it is (Imoplanta::contextoPlantas)
     */
    public function ler(array $imagens, string $contexto = ''): array
    {
        $esquema = ImoplantaCasa::esquema();
        $prompt = <<<PROMPT
        You are an architect reading Portuguese house plans (plantas, planta de implantação, alçados, cortes).
        The attached images are the drawings of ONE house. Build a precise 3D description of it.
        {$contexto}

        Work in this order, and get each step right before the next:
        1. Scale and measures. Work out the scale from printed dimensions (cotas), a scale bar, or standard sizes
           (doors 0.8–0.9 m, a 2-car garage ≈ 6 × 5.5 m). Read EVERY cota, corridors and halls included, and chain them
           across the plan: room widths plus wall thicknesses must add up to the overall dimension. Write each chain in
           "medidas" with the wall-axis coordinates it gives. A cota beats your eye: a 1.10 m corridor is 1.10 m.
        2. Walls. Plan axes: u grows to the right of the floor plan, v grows down; origin at the plot's top-left corner
           (or the house's, if no plot is drawn). Read every floor in the SAME axes (stack them). Walls by their centre
           line, at the coordinates from step 1; outer walls 0.30 m, inner 0.12 m unless the drawing says otherwise.
           Walls meet exactly at corners (share end points).
        3. Doors. Every door, archway (vao), glass door and garage door on its wall, with its position along the wall
           where the drawing shows its swing or gap. Every room must have a way in: check that each room's door opens
           onto a hall, corridor or room that itself leads to the entrance. Wall openings between rooms with no door leaf
           are "vao".
        4. Rooms: the space between their walls, with their use.
        5. Windows on their walls; sill heights and heights from the elevations/sections if given.
        6. Furniture and fittings the plan DRAWS (built-in wardrobes, kitchen worktops, bathtubs, shower trays, toilets,
           washbasins, beds, sofas, tables): each in its room's "moveis", with its footprint where it is drawn. Only what is
           drawn: never invent furniture; a room with none drawn gets "moveis": [].
        Then:
        - A level per storey (cave below 0 if any). Roof from the elevations/sections:
          flat, single, gable or hipped, with its outline including overhangs.
        - Terraces, porches (alpendres) and balconies (varandas) against the house have a roof over them ("coberto": true)
          unless they are rooftop terraces; open patios and pool decks away from the house do not. Upper-floor balconies:
          "altura" = that floor's level (they get a railing).
        - Outside: plot outline, terraces, pool, lawn, boundary walls, trees, the street, and neighbouring buildings
          when the site plan shows them (terraced/semi-detached houses share walls with their neighbours: include them).
        - "descricao": one sentence for a photographer — typology, storeys, roof, wall finishes, notable features.
        - If something is not drawn, make the most ordinary Portuguese choice; never leave out walls.

        Answer with ONLY the JSON object, in this schema:
        {$esquema}
        PROMPT;

        $casa = $this->pedir($prompt, $imagens);
        // One second look when the reading is plainly wrong (a room nobody can get into or walk through).
        if ($problemas = ImoplantaCasa::problemas($casa)) {
            $casa = $this->corrigir($casa, $problemas, $imagens, $contexto);
        }

        return $casa;
    }

    /**
     * @param  list<string>  $problemas  ImoplantaCasa::problemas of the first reading
     */
    private function corrigir(array $casa, array $problemas, array $imagens, string $contexto): array
    {
        $esquema = ImoplantaCasa::esquema();
        $atual = json_encode($casa, JSON_UNESCAPED_UNICODE);
        $lista = '- '.implode("\n- ", $problemas);
        $prompt = <<<PROMPT
        This JSON is your reading of the attached house plans (schema below). {$contexto}
        Checking it found these mistakes:
        {$lista}

        Look at the plans again where these rooms are. Re-read the cotas around them (rooms, corridors, halls, wall
        thicknesses) and move the walls to where the cotas put them; put the doors where the drawing shows them, so every
        room can be reached from the entrance. Fix what is wrong there and keep the rest of the house as it is.

        Current house:
        {$atual}

        Schema:
        {$esquema}

        Answer with ONLY the complete corrected JSON object.
        PROMPT;

        return $this->pedir($prompt, $imagens);
    }

    /**
     * The house with the customer's change applied (geometry only).
     *
     * @param  list<string>  $imagens  the plan images, for reference
     */
    public function alterar(array $casa, string $pedido, array $imagens, string $contexto = ''): array
    {
        $esquema = ImoplantaCasa::esquema();
        $atual = json_encode($casa, JSON_UNESCAPED_UNICODE);
        $prompt = <<<PROMPT
        This JSON describes a house read from the attached plan images (schema below). The customer asks for a change
        to the 3D model, in Portuguese. {$contexto}

        «{$pedido}»

        Apply exactly that change and nothing else: keep every other wall, opening, room and coordinate as it is.
        If the request corrects a mistake in reading the plans, check the plans to get it right. If it asks for
        something new (a pool, a pergola, a different roof), add it in the most plausible place and size.
        Keep walls meeting exactly at corners.

        Current house:
        {$atual}

        Schema:
        {$esquema}

        Answer with ONLY the complete updated JSON object.
        PROMPT;

        return $this->pedir($prompt, $imagens);
    }

    private function pedir(string $prompt, array $imagens): array
    {
        $envelope = $this->runClaude($prompt, null, array_filter([
            'images' => $imagens, 'maxTurns' => count($imagens) + 4, 'timeout' => 900,
            'model' => config('contentmachine.imoplanta.modelo_llm'),   // empty: the clips pipeline's model
        ]));
        $this->custos->llm($envelope);
        $texto = (string) ($envelope['result'] ?? '');
        $json = preg_match('/\{.*\}/s', $texto, $m) ? json_decode($m[0], true) : null;
        if (! is_array($json)) {
            throw new RuntimeException('O modelo não devolveu uma descrição válida da casa.');
        }

        return ImoplantaCasa::normalizar($json);
    }
}
