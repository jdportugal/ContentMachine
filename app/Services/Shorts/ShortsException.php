<?php

namespace App\Services\Shorts;

use RuntimeException;

/** Erro do serviço ShortsCreator (HTTP, job falhado ou timeout). */
class ShortsException extends RuntimeException {}
