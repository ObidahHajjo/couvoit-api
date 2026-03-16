<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use LogicException;

/**
 * Eloquent implementation of AddressRepositoryInterface.
 *
 * Handles persistence, retrieval and caching of Address aggregates.
 */
readonly class AddressEloquentRepository implements AddressRepositoryInterface
{
    public function __construct(
        private RepositoryCacheManager $cache
    ) {
    }

    /**
     * @inheritDoc
     */
    public function create(array $data): Address
    {
        $address = Address::query()->firstOrCreate(
            [
                'street_number' => $data['street_number'],
                'street' => $data['street'],
                'city_id' => $data['city_id'],
            ],
            $data
        );

        $address->load('city');
        $this->cache->putAddress($address);

        return $address;
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(int $id): Address
    {
        $address = $this->cache->rememberAddressById($id, fn () => Address::query()
            ->with('city')
            ->findOrFail($id));

        if (! $address instanceof Address) {
            throw new LogicException('Cached value is not an Address instance.');
        }

        return $address;
    }
}
