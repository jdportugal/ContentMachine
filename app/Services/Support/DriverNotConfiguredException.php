<?php

namespace App\Services\Support;

use RuntimeException;

/**
 * Lançada quando um driver real é pedido sem as credenciais necessárias.
 * Por defeito a aplicação usa o driver 'fake', por isso isto só ocorre
 * depois de alguém mudar a config para um driver real sem preencher as chaves.
 */
class DriverNotConfiguredException extends RuntimeException
{
    public static function for(string $servico, string $chave): self
    {
        return new self("O driver '{$servico}' requer a credencial '{$chave}'. Defina-a no .env ou volte ao driver 'fake'.");
    }
}
