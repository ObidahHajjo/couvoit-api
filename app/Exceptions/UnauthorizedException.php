<?php

namespace App\Exceptions;

use Exception;

class UnauthorizedException extends DomainException
{
    public function __construct(string $message = 'External service error')
    {
        parent::__construct($message, 401, 'EXTERNAL_SERVICE_ERROR');
    }
}
