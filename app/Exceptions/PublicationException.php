<?php

namespace App\Exceptions;

use App\Enums\PublicationFailureType;
use Exception;
use Throwable;

class PublicationException extends Exception
{
    public function __construct(
        string $message,
        public readonly PublicationFailureType $failureType,
        public readonly ?int $retryAfterSeconds = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }
}
