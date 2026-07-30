<?php

namespace App\Services\Support;

use RuntimeException;

/**
 * Thrown when a real driver is requested without the required credentials.
 * By default the app uses the 'fake' driver, so this only happens
 * after someone changes the config to a real driver without filling in the keys.
 */
class DriverNotConfiguredException extends RuntimeException
{
    public static function for(string $servico, string $chave): self
    {
        return new self("The '{$servico}' driver requires the '{$chave}' credential. Set it in .env or switch back to the 'fake' driver.");
    }
}
