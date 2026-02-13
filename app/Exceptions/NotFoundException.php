<?php

namespace App\Exceptions;

use Exception;

class NotFoundException extends DomainException
{
    public function __construct(string $message = 'Not found')
    {
        parent::__construct($message, 404, 'NOT_FOUND');
    }
}
