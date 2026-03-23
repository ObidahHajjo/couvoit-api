<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for missing resources.
 */
class NotFoundException extends DomainException
{
    /**
     * Create a new not found exception.
     */
    public function __construct(string $message = 'Not found')
    {
        parent::__construct($message, 404, 'NOT_FOUND');
    }
}
