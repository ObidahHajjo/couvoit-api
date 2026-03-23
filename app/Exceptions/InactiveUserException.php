<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain exception for inactive user access attempts.
 */
class InactiveUserException extends DomainException
{
    /**
     * Create a new inactive user exception.
     */
    public function __construct(string $message = 'Account inactive')
    {
        parent::__construct($message, 403, 'INACTIVE_ACCOUNT');
    }
}
