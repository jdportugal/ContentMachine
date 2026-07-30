<?php

namespace App\Services\Shorts;

use RuntimeException;

/** ShortsCreator service error (HTTP, failed job or timeout). */
class ShortsException extends RuntimeException {}
