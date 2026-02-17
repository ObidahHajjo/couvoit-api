<?php

namespace App\Services\Implementations;

use App\Exceptions\ValidationLogicException;
use App\Models\Person;
use App\Repositories\Interfaces\PersonRepositoryInterface;
use App\Repositories\Interfaces\TripRepositoryInterface;
use App\Services\Interfaces\PersonServiceInterface;
use Illuminate\Support\Collection;

/**
 * Class PersonService
 *
 * Application service responsible for person-related use cases:
 * - Listing users
 * - Viewing a profile
 * - Listing trips as driver
 * - Listing trips as passenger
 * - Updating own profile
 * - Soft deactivation
 * - Hard deletion
 *
 * Authorization rules are handled by policies in controllers.
 */
readonly class PersonService implements PersonServiceInterface
{
    /**
     * @param PersonRepositoryInterface $persons
     * @param TripRepositoryInterface $trips
     */
    public function __construct(
        private PersonRepositoryInterface $persons,
        private TripRepositoryInterface $trips,
    ) {}

    /**
     * @inheritDoc
     */
    public function list(): Collection
    {
        return $this->persons->all();
    }

    /**
     * @inheritDoc
     */
    public function show(Person $person): Person
    {
        return $person;
    }

    public function findById(int $id): Person
    {
        return $this->persons->findById($id);
    }

    /**
     * @inheritDoc
     */
    public function tripsAsDriver(Person $person): Collection
    {
        return $this->trips->listByDriver($person->id);
    }

    /**
     * @inheritDoc
     */
    public function tripsAsPassenger(Person $person): Collection
    {
        return $this->trips->listByPassenger($person->id);
    }

    /**
     * @inheritDoc
     */
    public function update(Person $person, array $data): Person
    {
        if(empty($data)) throw new ValidationLogicException("Nothing to update");

        if (array_key_exists('status', $data)) {
            $status = $data['status'];
            unset($data['status']);

            if ($status === 'DELETED') {
                $this->deactivate($person);
            } elseif ($status === 'ACTIVE') {
                $this->reactivate($person);
            }
        }

        if(!empty($data)) $this->persons->update($person->id, $data);
        return $this->persons->findById($person->id);
    }

    /**
     * @inheritDoc
     */
    public function softDelete(Person $person): void
    {
        $this->persons->delete($person->id);
    }

    public function deactivate(Person $person): void
    {
        $this->persons->update($person->id, ['is_active' => false]);
    }

    public function reactivate(Person $person): void
    {
        $this->persons->update($person->id, ['is_active' => true]);
    }
}
