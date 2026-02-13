<?php

namespace App\Resolvers\Interfaces;

interface AddressResolverInterface
{
    /**
     * Resolve an address from payload and return the Address id.
     *
     * Expected payload:
     * [
     *   'street_number' => string,
     *   'street_name'   => string,
     *   'postal_code'   => string,
     *   'city_name'     => string
     * ]
     */
    public function resolveId(array $payload): int;
}
