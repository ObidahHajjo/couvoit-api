<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for internal application failures.
 */
class InternalAppException extends DomainException
{
    /**
     * Create a new internal application exception.
     */
    public function __construct(string $message = 'Internal server error')
    {
        parent::__construct($message, 500, 'INTERNAL_ERROR');
    }
}
