<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for forbidden operations.
 */
class ForbiddenException extends DomainException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403, 'FORBIDDEN');
    }
}
