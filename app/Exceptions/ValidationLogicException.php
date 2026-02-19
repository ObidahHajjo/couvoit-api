<?php

namespace App\Exceptions;

use Exception;

class ValidationLogicException extends DomainException
{
    public function __construct(string $message = 'Unprocessable entity')
    {
        parent::__construct($message, 422, 'UNPROCESSABLE_ENTITY');
    }
}
