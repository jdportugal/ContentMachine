<?php

namespace App\Services\Publicacoes;

/**
 * Acesso ao registo declarativo de tipos de publicação
 * (config `contentmachine.publicacoes.tipos`). É a única fonte de verdade
 * sobre os formatos; a oficina, o planeador e o renderizador leem daqui.
 */
class PublicacaoKinds
{
    /** @return array<string,array<string,mixed>> tipos indexados por identificador */
    public function all(): array
    {
        return (array) config('contentmachine.publicacoes.tipos', []);
    }

    /** @return array<string,mixed>|null */
    public function get(string $tipo): ?array
    {
        return $this->all()[$tipo] ?? null;
    }

    public function exists(string $tipo): bool
    {
        return array_key_exists($tipo, $this->all());
    }

    /** 'single' | 'carousel' — 'single' por omissão para tipos desconhecidos. */
    public function formato(string $tipo): string
    {
        return $this->get($tipo)['formato'] ?? 'single';
    }

    /** @return array{min:int,max:int} limites de cartões do tipo. */
    public function cartoes(string $tipo): array
    {
        $c = $this->get($tipo)['cartoes'] ?? ['min' => 1, 'max' => 1];

        return ['min' => (int) ($c['min'] ?? 1), 'max' => (int) ($c['max'] ?? 1)];
    }
}
