<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for external service failures.
 */
class ExternalServiceException extends DomainException
{
    public function __construct(string $message = 'External service error')
    {
        parent::__construct($message, 502, 'EXTERNAL_SERVICE_ERROR');
    }
}
