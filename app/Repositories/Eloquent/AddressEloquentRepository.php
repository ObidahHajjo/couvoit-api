<?php

namespace App\Repositories\Eloquent;

use App\Models\Address;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Eloquent implementation of AddressRepositoryInterface.
 *
 * Handles persistence, retrieval and caching of Address aggregates.
 */
class AddressEloquentRepository implements AddressRepositoryInterface
{
    private const TTL_SECONDS = 3600;

    /*
    |--------------------------------------------------------------------------
    | Cache Tags
    |--------------------------------------------------------------------------
    */

    private function tagAddresses(): array
    {
        return ['addresses'];
    }

    private function tagAddress(int $id): array
    {
        return ['addresses', "address:$id"];
    }

    /*
    |--------------------------------------------------------------------------
    | Cache Keys
    |--------------------------------------------------------------------------
    */

    private function keyById(int $id): string
    {
        return "addresses:$id";
    }

    /*
    |--------------------------------------------------------------------------
    | Repository Methods
    |--------------------------------------------------------------------------
    */

    /**
     * @inheritDoc
     */
    public function create(array $data): Address
    {
        $address = Address::query()->create($data);

        // Cache freshly created address
        Cache::tags($this->tagAddress($address->id))
            ->put($this->keyById($address->id), $address->loadMissing('city'), self::TTL_SECONDS);

        return $address;
    }

    /**
     * @inheritDoc
     */
    public function findOrFail(int $id): Address
    {
        $address = Cache::tags($this->tagAddress($id))
            ->remember(
                $this->keyById($id),
                self::TTL_SECONDS,
                fn () => Address::query()
                    ->with(['city'])
                    ->findOrFail($id)
            );

        if (!$address instanceof Address) throw new \LogicException('Cached value is not an Address instance.');

        return $address;
    }

}
