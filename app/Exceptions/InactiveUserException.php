<?php

namespace App\Exceptions;

use Exception;

class InactiveUserException extends DomainException
{
    public function __construct(string $message = 'Account inactive')
    {
        parent::__construct($message, 403, 'INACTIVE_ACCOUNT');
    }
}
