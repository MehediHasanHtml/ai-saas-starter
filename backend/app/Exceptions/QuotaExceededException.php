<?php

namespace App\Exceptions;

use RuntimeException;

/** Rendered as HTTP 402 with code "quota_exceeded" (see bootstrap/app.php). */
class QuotaExceededException extends RuntimeException
{
    public function __construct(string $message, public readonly string $limit)
    {
        parent::__construct($message);
    }
}
