<?php

namespace App\Services\Publicacoes\Dto;

/**
 * Um cartão planeado de uma publicação (capa ou conteúdo).
 */
class SlidePlano
{
    /**
     * @param  array<int,int>  $referencias  índices (na pool de referências) das
     *   imagens que a IA associou a este cartão. Semeiam os anexos na oficina.
     */
    public function __construct(
        public int $ordem,
        public string $titulo,
        public string $texto,
        public array $referencias = [],
    ) {}

    /** @param array<string,mixed> $dados */
    public static function fromArray(array $dados, int $ordemPadrao = 1): self
    {
        $refs = is_array($dados['referencias'] ?? null) ? $dados['referencias'] : [];
        $refs = array_values(array_filter(array_map(
            fn ($r) => is_numeric($r) ? (int) $r : null,
            $refs,
        ), fn ($r) => $r !== null && $r >= 0));

        return new self(
            ordem: (int) ($dados['ordem'] ?? $ordemPadrao),
            titulo: trim((string) ($dados['titulo'] ?? '')),
            texto: trim((string) ($dados['texto'] ?? '')),
            referencias: $refs,
        );
    }
}
