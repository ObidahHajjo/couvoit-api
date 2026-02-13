<?php

namespace App\Exceptions;

use Exception;

class InternalAppException extends DomainException
{
    public function __construct(string $message = 'Internal server error')
    {
        parent::__construct($message, 500, 'INTERNAL_ERROR');
    }
}
