<?php

namespace App\Exceptions;

use Exception;

class ConflictException extends DomainException
{
    public function __construct(string $message = 'Conflict')
    {
        parent::__construct($message, 409, 'CONFLICT');
    }
}
