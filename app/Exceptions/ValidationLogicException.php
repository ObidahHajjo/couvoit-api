<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for business validation failures.
 */
class ValidationLogicException extends DomainException
{
    public function __construct(string $message = 'Unprocessable entity')
    {
        parent::__construct($message, 422, 'UNPROCESSABLE_ENTITY');
    }
}
