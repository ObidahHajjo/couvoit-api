<?php

namespace App\Resolvers\Implementations;

use App\Exceptions\ValidationLogicException;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\CityRepositoryInterface;
use App\Resolvers\Interfaces\AddressResolverInterface;

/**
 * Resolves an Address identifier from an incoming payload.
 *
 * Responsibilities:
 * - Validate minimal address payload fields
 * - Upsert the City (name + postal_code)
 * - Create the Address row and return its id
 */
final readonly class AddressResolver implements AddressResolverInterface
{
    /**
     * @param CityRepositoryInterface    $cities
     * @param AddressRepositoryInterface $addresses
     */
    public function __construct(
        private CityRepositoryInterface $cities,
        private AddressRepositoryInterface $addresses,
    ) {}

    /** @inheritDoc */
    public function resolveId(array $payload): int
    {
        $cityName = trim((string) ($payload['city_name'] ?? ''));
        $postal   = trim((string) ($payload['postal_code'] ?? ''));
        $street   = trim((string) ($payload['street_name'] ?? ''));
        $number   = trim((string) ($payload['street_number'] ?? ''));

        if ($cityName === '' || $postal === '' || $street === '' || $number === '') {
            throw new ValidationLogicException('Invalid address payload.');
        }

        $city = $this->cities->firstOrCreate(
            mb_strtolower($cityName),
            $postal
        );

        $address = $this->addresses->create([
            'street'        => mb_strtolower($street),
            'street_number' => $number,
            'city_id'       => $city->id,
        ]);

        return (int) $address->id;
    }
}
