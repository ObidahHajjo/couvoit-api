<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Support\Cache\RepositoryCacheManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use LogicException;

/**
 * Eloquent implementation of AddressRepositoryInterface.
 *
 * Handles persistence, retrieval and caching of Address aggregates.
 *
 * @author Covoiturage API
 *
 * @description Repository for managing Address entities with caching support.
 */
readonly class AddressEloquentRepository implements AddressRepositoryInterface
{
    /**
     * Create a new address repository instance.
     */
    public function __construct(
        private RepositoryCacheManager $cache
    ) {}

    /**
     * Create a new address or return existing one.
     *
     * @param  array<string, mixed>  $data  Address data containing street_number, street, and city_id
     * @return Address The created or existing Address instance with city relation loaded
     *
     * @throws ModelNotFoundException When city_id references non-existent city
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
     * Find an address by ID or fail.
     *
     * @param  int  $id  The address ID to find
     * @return Address The Address instance with city relation loaded
     *
     * @throws ModelNotFoundException When address not found
     * @throws LogicException When cached value is not an Address instance
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
