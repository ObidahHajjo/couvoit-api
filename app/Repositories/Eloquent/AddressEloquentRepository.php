<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;

class AddressEloquentRepository implements AddressRepositoryInterface
{
    public function create(array $data): Address
    {
        return Address::query()->create($data);
    }
}
