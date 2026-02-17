<?php

namespace App\Repositories\Interfaces;

use App\Models\Address;

interface AddressRepositoryInterface
{
    public function create(array $data): Address;

    public function findOrFail(int $id): Address;
}
