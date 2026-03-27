<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for forbidden operations.
 */
class ForbiddenException extends DomainException
{
    /**
     * Create a new forbidden exception.
     */
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403, 'FORBIDDEN');
    }
}
