<?php

namespace App\Repositories\Interfaces;

use App\Models\Address;

interface AddressRepositoryInterface
{
    /**
     * Persist a new Address.
     *
     * @param array<string,mixed> $data
     * @return Address
     */
    public function create(array $data): Address;

    /**
     * Retrieve an Address by its identifier or fail.
     *
     * Ensures the related city is loaded to avoid N+1 issues
     * when consumed by higher layers (e.g., resources).
     *
     * @param int $id
     * @return Address
     */
    public function findOrFail(int $id): Address;
}
