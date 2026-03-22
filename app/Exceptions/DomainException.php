<?php

namespace App\Exceptions;

use Exception;

/**
 * Base exception for domain-level API errors.
 */
class DomainException extends Exception
{
    protected int $status = 400;

    protected String $codeName;
    public function __construct(string $message, int $status = 400, $codeName = "DOMAIN_ERROR")
    {
        parent::__construct($message);
        $this->status = $status;
        $this->codeName = $codeName;
    }


    public function status(): int
    {
        return $this->status;
    }

    public function codeName(): string
    {
        return $this->codeName;
    }
}
