<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class PostContentGenerationException extends Exception
{
    public function __construct(string $message, public readonly int $status = 502, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);
    }
}
