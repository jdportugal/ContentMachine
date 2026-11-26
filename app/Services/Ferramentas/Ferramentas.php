<?php

namespace App\Services\Ferramentas;

/**
 * The AI video tools: one photo (or a floor plan) → a start and an end frame the customer approves → one
 * Kling clip. Sold as packs of "vídeos IA" (compras.videos_ia), any tool. The texts here are what the landing
 * page and the customer pages show; `ai` is which frame the AI makes (the other is the customer's upload, or for
 * the plan, drawn by code); `segundos` is the clip length (it sets the worst-case cost: see the design doc).
 */
class Ferramentas
{
    /** @return array<string, array{nome:string,linha:string,texto:string,envio:string,inicio:string,fim:string,ai:string,segundos:int}> */
    public static function lista(): array
    {
        return [
            'drone' => [
                'nome' => 'Vista drone', 'linha' => 'A câmara sobe do nível da rua até uma vista de drone baixa.',
                'texto' => 'A partir de uma fotografia tirada da rua, a câmara eleva-se suavemente até alguns metros de altura e mostra a casa, o terreno e a envolvente, como um voo de drone.',
                'envio' => 'Fotografia da fachada ou do exterior', 'inicio' => 'A sua fotografia', 'fim' => 'Vista de drone', 'ai' => 'fim', 'segundos' => 10,
            ],
            'limpeza' => [
                'nome' => 'Limpeza IA', 'linha' => 'Objetos e desarrumação desaparecem, fica o espaço limpo.',
                'texto' => 'Caixas, roupa, carros, caixotes do lixo: tudo o que distrai sai de cena, peça a peça, e o espaço fica arrumado e pronto a mostrar. As paredes, o chão e as janelas não mudam.',
                'envio' => 'Fotografia da divisão ou do exterior', 'inicio' => 'A sua fotografia', 'fim' => 'Espaço limpo', 'ai' => 'fim', 'segundos' => 10,
            ],
            'remodelacao' => [
                'nome' => 'Remodelação IA', 'linha' => 'Timelapse da remodelação, do estado atual ao renovado.',
                'texto' => 'Mostre o potencial de um imóvel para recuperar: a divisão transforma-se, em timelapse, numa versão remodelada. Pode dizer-nos o estilo que quer.',
                'envio' => 'Fotografia da divisão a remodelar', 'inicio' => 'Estado atual', 'fim' => 'Remodelado', 'ai' => 'fim', 'segundos' => 10,
            ],
            'construcao' => [
                'nome' => 'Construção IA', 'linha' => 'Operários constroem a casa, do terreno vazio ao fim.',
                'texto' => 'Do terreno vazio à casa acabada, com operários em obra: fundações, estrutura, paredes, telhado e acabamentos, em timelapse. Ideal para construção nova.',
                'envio' => 'Fotografia da casa acabada', 'inicio' => 'Terreno vazio', 'fim' => 'A sua fotografia', 'ai' => 'inicio', 'segundos' => 15,
            ],
            'planta' => [
                'nome' => 'Planta animada', 'linha' => 'A planta 2D passa a 3D, ergue-se e enche-se de mobília.',
                'texto' => 'Envie a planta de um piso. A casa aparece vista de cima: primeiro o chão e as paredes, depois a mobília a entrar no lugar, com som.',
                'envio' => 'Planta do piso', 'inicio' => 'Terreno', 'fim' => 'Planta 3D', 'ai' => 'fim', 'segundos' => 10,
            ],
        ];
    }

    /** The example clip and poster a tool shows on the landing page (public/exemplos-ia/<tool>.mp4|.jpg), when made. */
    public static function exemplo(string $ferramenta): array
    {
        $f = fn (string $ext) => is_file(public_path("exemplos-ia/{$ferramenta}.{$ext}")) ? asset("exemplos-ia/{$ferramenta}.{$ext}") : null;

        return ['video' => $f('mp4'), 'poster' => $f('jpg')];
    }
}
