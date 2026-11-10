<?php

namespace App\Services\Marketing;

/** The visitor's answer to the cookie banner: measurement (Meta, Google) only with a yes. */
class Consentimento
{
    public const COOKIE = 'imocena_cookies';

    public static function medicao(): bool
    {
        return request()->cookie(self::COOKIE) === 'sim';
    }
}
