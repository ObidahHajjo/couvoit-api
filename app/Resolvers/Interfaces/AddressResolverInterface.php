<?php

namespace App\Resolvers\Interfaces;

use App\Exceptions\ValidationLogicException;

/**
 * Contract for resolving persisted address identifiers from request payloads.
 */
interface AddressResolverInterface
{

    /**
     * Resolve (and persist) an address and return its id.
     *
     * Required payload keys:
     * - city_name
     * - postal_code
     * - street_name
     * - street_number
     *
     * @param array<string,mixed> $payload
     * @return int
     *
     * @throws ValidationLogicException When the payload is missing required fields.
     */
    public function resolveId(array $payload): int;
}
