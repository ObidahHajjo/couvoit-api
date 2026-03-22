<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for conflicting resource state.
 */
class ConflictException extends DomainException
{
    public function __construct(string $message = 'Conflict')
    {
        parent::__construct($message, 409, 'CONFLICT');
    }
}
