<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for authentication failures.
 */
class UnauthorizedException extends DomainException
{
    /**
     * Create a new unauthorized exception.
     */
    public function __construct(string $message = 'External service error')
    {
        parent::__construct($message, 401, 'EXTERNAL_SERVICE_ERROR');
    }
}
