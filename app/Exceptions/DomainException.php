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
    /**
     * Create a new domain exception instance.
     */
    public function __construct(string $message, int $status = 400, $codeName = "DOMAIN_ERROR")
    {
        parent::__construct($message);
        $this->status = $status;
        $this->codeName = $codeName;
    }


    /**
     * Get the HTTP status code for the exception.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * Get the application error code name.
     */
    public function codeName(): string
    {
        return $this->codeName;
    }
}
