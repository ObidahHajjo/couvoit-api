<?php

namespace App\Support\Cache;

use App\Models\Car;
use App\Models\CarModel;
use App\Models\Person;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Coordinates cache invalidation for repository-level cache entries.
 *
 * @author Covoiturage API Team
 *
 * @description Manages caching and cache invalidation for various entity types using cache tags.
 */
class RepositoryCacheManager
{
    public const TTL_LONG = 360;

    public const TTL_LIST = 60;

    /**
     * Remember a value for the given cache tags and key.
     */
    public function remember(array $tags, string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * Store a value for the given cache tags and key.
     */
    public function put(array $tags, string $key, mixed $value, int $ttl = self::TTL_LONG): void
    {
        Cache::tags($tags)->put($key, $value, $ttl);
    }

    /**
     * Forget a cache entry for the given tags and key.
     */
    public function forget(array $tags, string $key): void
    {
        Cache::tags($tags)->forget($key);
    }

    /**
     * Flush all cache entries for the given tags.
     */
    public function flush(array $tags): void
    {
        Cache::tags($tags)->flush();
    }

    /*
    |--------------------------------------------------------------------------
    | Persons
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for person.
     */
    public function personTags(?int $id = null): array
    {
        return $id === null ? ['persons'] : ['persons', "person:$id"];
    }

    /**
     * Get the cache key for person.
     */
    public function personKey(int $id): string
    {
        return "persons:$id";
    }

    /**
     * Get the cache key for persons all.
     */
    public function personsAllKey(): string
    {
        return 'persons:all';
    }

    /**
     * Remember cached persons all.
     */
    public function rememberPersonsAll(Closure $callback): Collection
    {
        /** @var Collection $people */
        $people = $this->remember(
            $this->personTags(),
            $this->personsAllKey(),
            self::TTL_LONG,
            $callback
        );

        return $people;
    }

    /**
     * Remember cached person by id.
     */
    public function rememberPersonById(int $id, Closure $callback): Person
    {
        /** @var Person $person */
        $person = $this->remember(
            $this->personTags($id),
            $this->personKey($id),
            self::TTL_LONG,
            $callback
        );

        return $person;
    }

    /**
     * Store cached person.
     */
    public function putPerson(Person $person): void
    {
        $this->put(
            $this->personTags($person->id),
            $this->personKey($person->id),
            $person
        );
    }

    /**
     * Forget cached person.
     */
    public function forgetPerson(int $id): void
    {
        $this->forget($this->personTags($id), $this->personKey($id));
    }

    /**
     * Forget cached persons all.
     */
    public function forgetPersonsAll(): void
    {
        $this->forget($this->personTags(), $this->personsAllKey());
    }

    /**
     * Invalidate cached person list and item.
     */
    public function invalidatePersonListAndItem(int $id): void
    {
        $this->forgetPerson($id);
        $this->forgetPersonsAll();
    }

    /**
     * Invalidate cached persons by car id.
     */
    public function invalidatePersonsByCarId(int $carId): void
    {
        $personIds = Person::query()
            ->where('car_id', $carId)
            ->pluck('id');
        foreach ($personIds as $personId) {
            $this->forgetPerson((int) $personId);
            Cache::tags($this->personTags((int) $personId))->flush();
        }
        $this->forgetPersonsAll();
    }

    /*
    |--------------------------------------------------------------------------
    | Cars
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for car.
     */
    public function carTags(?int $id = null): array
    {
        return $id === null ? ['cars'] : ['cars', "car:$id"];
    }

    /**
     * Get the cache key for car.
     */
    public function carKey(int $id): string
    {
        return "cars:$id";
    }

    /**
     * Get the cache key for cars all.
     */
    public function carsAllKey(): string
    {
        return 'cars:all';
    }

    /**
     * Remember cached cars all.
     */
    public function rememberCarsAll(Closure $callback): Collection
    {
        /** @var Collection $cars */
        $cars = $this->remember(
            $this->carTags(),
            $this->carsAllKey(),
            self::TTL_LONG,
            $callback
        );

        return $cars;
    }

    /**
     * Remember cached car by id.
     */
    public function rememberCarById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->carTags($id),
            $this->carKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Store cached car.
     */
    public function putCar(Car $car): void
    {
        $this->put(
            $this->carTags($car->id),
            $this->carKey($car->id),
            $car
        );
    }

    /**
     * Forget cached car.
     */
    public function forgetCar(int $id): void
    {
        $this->forget($this->carTags($id), $this->carKey($id));
    }

    /**
     * Forget cached cars all.
     */
    public function forgetCarsAll(): void
    {
        $this->forget($this->carTags(), $this->carsAllKey());
    }

    /**
     * Invalidate cached car list and item.
     */
    public function invalidateCarListAndItem(int $id): void
    {
        $this->forgetCar($id);
        $this->forgetCarsAll();
        $this->invalidatePersonsByCarId($id);
    }

    /**
     * Invalidate cached cars and persons by model id.
     */
    public function invalidateCarsAndPersonsByModelId(int $modelId): void
    {
        $carIds = Car::query()
            ->where('model_id', $modelId)
            ->pluck('id');

        $this->invalidateCarsAndPersonsByCarIds($carIds->all());
    }

    /**
     * Invalidate cached cars and persons by color id.
     */
    public function invalidateCarsAndPersonsByColorId(int $colorId): void
    {
        $carIds = Car::query()
            ->where('color_id', $colorId)
            ->pluck('id');

        $this->invalidateCarsAndPersonsByCarIds($carIds->all());
    }

    /**
     * Invalidate cached cars and persons by brand id.
     */
    public function invalidateCarsAndPersonsByBrandId(int $brandId): void
    {
        $modelIds = CarModel::query()
            ->where('brand_id', $brandId)
            ->pluck('id');

        $carIds = Car::query()
            ->whereIn('model_id', $modelIds)
            ->pluck('id');

        $this->invalidateCarsAndPersonsByCarIds($carIds->all());
    }

    /**
     * Invalidate cached cars and persons by type id.
     */
    public function invalidateCarsAndPersonsByTypeId(int $typeId): void
    {
        $modelIds = CarModel::query()
            ->where('type_id', $typeId)
            ->pluck('id');

        $carIds = Car::query()
            ->whereIn('model_id', $modelIds)
            ->pluck('id');

        $this->invalidateCarsAndPersonsByCarIds($carIds->all());
    }

    /**
     * @param  array<int,int|string>  $carIds
     */
    public function invalidateCarsAndPersonsByCarIds(array $carIds): void
    {
        foreach ($carIds as $carId) {
            $carId = (int) $carId;
            $this->forgetCar($carId);
            $this->invalidatePersonsByCarId($carId);
        }

        $this->forgetCarsAll();
    }

    /*
    |--------------------------------------------------------------------------
    | Addresses
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for address.
     */
    public function addressTags(int $id): array
    {
        return ['addresses', "address:$id"];
    }

    /**
     * Get the cache key for address.
     */
    public function addressKey(int $id): string
    {
        return "addresses:$id";
    }

    /**
     * Remember cached address by id.
     */
    public function rememberAddressById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->addressTags($id),
            $this->addressKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Store cached address.
     */
    public function putAddress(Model $address): void
    {
        $this->put(
            $this->addressTags((int) $address->getKey()),
            $this->addressKey((int) $address->getKey()),
            $address
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Brands
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for brand.
     */
    public function brandTags(?int $id = null): array
    {
        return $id === null ? ['brands'] : ['brands', "brand:$id"];
    }

    /**
     * Get the cache key for brand.
     */
    public function brandKey(int $id): string
    {
        return "brands:$id";
    }

    /**
     * Get the cache key for brands all.
     */
    public function brandsAllKey(): string
    {
        return 'brands:all';
    }

    /**
     * Remember cached brands all.
     */
    public function rememberBrandsAll(Closure $callback): Collection
    {
        /** @var Collection $brands */
        $brands = $this->remember(
            $this->brandTags(),
            $this->brandsAllKey(),
            self::TTL_LONG,
            $callback
        );

        return $brands;
    }

    /**
     * Remember cached brand by id.
     */
    public function rememberBrandById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->brandTags($id),
            $this->brandKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Store cached brand.
     */
    public function putBrand(Model $brand): void
    {
        $id = (int) $brand->getKey();

        $this->put($this->brandTags($id), $this->brandKey($id), $brand);
    }

    /**
     * Forget cached brand.
     */
    public function forgetBrand(int $id): void
    {
        $this->forget($this->brandTags($id), $this->brandKey($id));
    }

    /**
     * Forget cached brands all.
     */
    public function forgetBrandsAll(): void
    {
        $this->forget($this->brandTags(), $this->brandsAllKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for model.
     */
    public function modelTags(?int $id = null): array
    {
        return $id === null ? ['models'] : ['models', "model:$id"];
    }

    /**
     * Get the cache tags for model by brand.
     */
    public function modelByBrandTags(int $brandId): array
    {
        return ['models', "brand:$brandId"];
    }

    /**
     * Get the cache tags for model by name.
     */
    public function modelByNameTags(string $name): array
    {
        return ['models', 'name:'.$this->normalize($name)];
    }

    /**
     * Get the cache key for model.
     */
    public function modelKey(int $id): string
    {
        return "models:$id";
    }

    /**
     * Get the cache key for models all.
     */
    public function modelsAllKey(): string
    {
        return 'models:all';
    }

    /**
     * Get the cache key for model by brand.
     */
    public function modelByBrandKey(int $brandId): string
    {
        return "models:brand:$brandId";
    }

    /**
     * Get the cache key for model by name.
     */
    public function modelByNameKey(string $name): string
    {
        return 'models:name:'.$this->normalize($name);
    }

    /**
     * Remember cached models all.
     */
    public function rememberModelsAll(Closure $callback): Collection
    {
        /** @var Collection $models */
        $models = $this->remember(
            $this->modelTags(),
            $this->modelsAllKey(),
            self::TTL_LONG,
            $callback
        );

        return $models;
    }

    /**
     * Remember cached model by id.
     */
    public function rememberModelById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->modelTags($id),
            $this->modelKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Remember cached model by name.
     */
    public function rememberModelByName(string $name, Closure $callback): mixed
    {
        return $this->remember(
            $this->modelByNameTags($name),
            $this->modelByNameKey($name),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Remember cached models by brand.
     */
    public function rememberModelsByBrand(int $brandId, Closure $callback): Collection
    {
        /** @var Collection $models */
        $models = $this->remember(
            $this->modelByBrandTags($brandId),
            $this->modelByBrandKey($brandId),
            self::TTL_LONG,
            $callback
        );

        return $models;
    }

    /**
     * Store cached model.
     */
    public function putModel(CarModel $model): void
    {
        $this->put($this->modelTags($model->id), $this->modelKey($model->id), $model);
        $this->put($this->modelByNameTags($model->name), $this->modelByNameKey($model->name), $model);
    }

    /**
     * Forget cached model.
     */
    public function forgetModel(int $id): void
    {
        $this->forget($this->modelTags($id), $this->modelKey($id));
    }

    /**
     * Forget cached models all.
     */
    public function forgetModelsAll(): void
    {
        $this->forget($this->modelTags(), $this->modelsAllKey());
    }

    /**
     * Forget cached models by brand.
     */
    public function forgetModelsByBrand(int $brandId): void
    {
        $this->forget($this->modelByBrandTags($brandId), $this->modelByBrandKey($brandId));
    }

    /**
     * Forget cached model by name.
     */
    public function forgetModelByName(string $name): void
    {
        $this->forget($this->modelByNameTags($name), $this->modelByNameKey($name));
    }

    /*
    |--------------------------------------------------------------------------
    | Cities
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for city.
     */
    public function cityTags(string $name, string $postalCode): array
    {
        return ['cities', 'city:'.$this->normalize($name).':'.trim($postalCode)];
    }

    /**
     * Get the cache key for city.
     */
    public function cityKey(string $name, string $postalCode): string
    {
        return 'cities:'.$this->normalize($name).':'.trim($postalCode);
    }

    /**
     * Get the cache tags for city postcodes.
     */
    public function cityPostcodesTags(): array
    {
        return ['cities', 'cities:postcodes'];
    }

    /**
     * Get the cache key for city postcodes.
     */
    public function cityPostcodesKey(): string
    {
        return 'cities:postcodes';
    }

    /**
     * Remember cached city.
     */
    public function rememberCity(string $name, string $postalCode, Closure $callback): mixed
    {
        return $this->remember(
            $this->cityTags($name, $postalCode),
            $this->cityKey($name, $postalCode),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Store cached city.
     */
    public function putCity(Model $city, string $name, string $postalCode): void
    {
        $this->put(
            $this->cityTags($name, $postalCode),
            $this->cityKey($name, $postalCode),
            $city
        );
    }

    /**
     * Forget cached city.
     */
    public function forgetCity(string $name, string $postalCode): void
    {
        $this->forget(
            $this->cityTags($name, $postalCode),
            $this->cityKey($name, $postalCode)
        );
    }

    /**
     * Forget cached city postcodes.
     */
    public function forgetCityPostcodes(): void
    {
        $this->forget($this->cityPostcodesTags(), $this->cityPostcodesKey());
    }

    /**
     * Remember cached city postcodes.
     */
    public function rememberCityPostcodes(Closure $callback): Collection
    {
        /** @var Collection $postcodes */
        $postcodes = $this->remember(
            $this->cityPostcodesTags(),
            $this->cityPostcodesKey(),
            self::TTL_LONG,
            $callback
        );

        return $postcodes;
    }

    /*
    |--------------------------------------------------------------------------
    | Colors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for color.
     */
    public function colorTags(?int $id = null): array
    {
        return $id === null ? ['colors'] : ['colors', "color:$id"];
    }

    /**
     * Get the cache tags for color by hex.
     */
    public function colorByHexTags(string $hex): array
    {
        return ['colors', 'hex:'.$this->normalize($hex)];
    }

    /**
     * Get the cache tags for color by name.
     */
    public function colorByNameTags(string $name): array
    {
        return ['colors', 'name:'.$this->normalize($name)];
    }

    /**
     * Get the cache key for color.
     */
    public function colorKey(int $id): string
    {
        return "colors:$id";
    }

    /**
     * Get the cache key for colors all.
     */
    public function colorsAllKey(): string
    {
        return 'colors:all';
    }

    /**
     * Get the cache key for color by hex.
     */
    public function colorByHexKey(string $hex): string
    {
        return 'colors:hex:'.$this->normalize($hex);
    }

    /**
     * Get the cache key for color by name.
     */
    public function colorByNameKey(string $name): string
    {
        return 'colors:name:'.$this->normalize($name);
    }

    /**
     * Remember cached colors all.
     */
    public function rememberColorsAll(Closure $callback): Collection
    {
        /** @var Collection $colors */
        $colors = $this->remember(
            $this->colorTags(),
            $this->colorsAllKey(),
            self::TTL_LONG,
            $callback
        );

        return $colors;
    }

    /**
     * Remember cached color by id.
     */
    public function rememberColorById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->colorTags($id),
            $this->colorKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Remember cached color by name.
     */
    public function rememberColorByName(string $name, Closure $callback): mixed
    {
        return $this->remember(
            $this->colorByNameTags($name),
            $this->colorByNameKey($name),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Remember cached color by hex.
     */
    public function rememberColorByHex(string $hex, Closure $callback): mixed
    {
        return $this->remember(
            $this->colorByHexTags($hex),
            $this->colorByHexKey($hex),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Store cached color.
     */
    public function putColor(Model $color, string $name, string $hex): void
    {
        $id = (int) $color->getKey();

        $this->put($this->colorTags($id), $this->colorKey($id), $color);
        $this->put($this->colorByNameTags($name), $this->colorByNameKey($name), $color);
        $this->put($this->colorByHexTags($hex), $this->colorByHexKey($hex), $color);
    }

    /**
     * Forget cached color.
     */
    public function forgetColor(int $id): void
    {
        $this->forget($this->colorTags($id), $this->colorKey($id));
    }

    /**
     * Forget cached color by name.
     */
    public function forgetColorByName(string $name): void
    {
        $this->forget($this->colorByNameTags($name), $this->colorByNameKey($name));
    }

    /**
     * Forget cached color by hex.
     */
    public function forgetColorByHex(string $hex): void
    {
        $this->forget($this->colorByHexTags($hex), $this->colorByHexKey($hex));
    }

    /**
     * Forget cached colors all.
     */
    public function forgetColorsAll(): void
    {
        $this->forget($this->colorTags(), $this->colorsAllKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Trips
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for trip.
     */
    public function tripTags(?int $id = null): array
    {
        return $id === null ? ['trips'] : ['trips', "trip:$id"];
    }

    /**
     * Get the cache tags for trip search.
     */
    public function tripSearchTags(): array
    {
        return ['trips', 'trips:search'];
    }

    /**
     * Get the cache tags for trip person.
     */
    public function tripPersonTags(int $personId): array
    {
        return ['trips', "person:$personId"];
    }

    /**
     * Get the cache tags for trip passengers.
     */
    public function tripPassengersTags(int $tripId): array
    {
        return ['trips', "trip:$tripId", 'reservations', 'persons'];
    }

    /**
     * Get the cache key for trip.
     */
    public function tripKey(int $id): string
    {
        return "trips:$id";
    }

    /**
     * Get the cache key for trip passengers.
     */
    public function tripPassengersKey(int $id): string
    {
        return "trips:$id:passengers";
    }

    /**
     * Get the cache key for trip search.
     */
    public function tripSearchKey(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        ?string $tripTime,
        int $perPage,
        int $page,
        ?int $excludePersonId = null
    ): string {
        return sprintf(
            'trips:search:%s:%s:%s:%s:per:%d:page:%d:exc:%s',
            $this->normalize($startingCity ?? 'any'),
            $this->normalize($arrivalCity ?? 'any'),
            $tripDate ?? 'any',
            $tripTime ?? 'any',
            $perPage,
            $page,
            $excludePersonId ?? 'none'
        );
    }

    /**
     * Get the cache key for trip driver list.
     */
    public function tripDriverListKey(int $personId): string
    {
        return "trips:driver:$personId";
    }

    /**
     * Get the cache key for trip passenger list.
     */
    public function tripPassengerListKey(int $personId): string
    {
        return "trips:passenger:$personId";
    }

    /**
     * Remember cached trip by id.
     */
    public function rememberTripById(int $id, Closure $callback): mixed
    {
        return $this->remember($this->tripTags($id), $this->tripKey($id), self::TTL_LONG, $callback);
    }

    /**
     * Remember cached trip passengers.
     */
    public function rememberTripPassengers(int $id, Closure $callback): Collection
    {
        /** @var Collection $passengers */
        $passengers = $this->remember(
            $this->tripPassengersTags($id),
            $this->tripPassengersKey($id),
            self::TTL_LONG,
            $callback
        );

        return $passengers;
    }

    /**
     * Remember cached trip search.
     */
    public function rememberTripSearch(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        ?string $tripTime,
        int $perPage,
        int $page,
        Closure $callback,
        ?int $excludePersonId = null
    ): LengthAwarePaginator {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->remember(
            $this->tripSearchTags(),
            $this->tripSearchKey($startingCity, $arrivalCity, $tripDate, $tripTime, $perPage, $page, $excludePersonId),
            self::TTL_LONG,
            $callback
        );

        return $paginator;
    }

    /**
     * Remember cached driver trips.
     */
    public function rememberDriverTrips(int $personId, Closure $callback): Collection
    {
        /** @var Collection $trips */
        $trips = $this->remember(
            array_merge($this->tripPersonTags($personId), ['persons']),
            $this->tripDriverListKey($personId),
            self::TTL_LIST,
            $callback
        );

        return $trips;
    }

    /**
     * Remember cached passenger trips.
     */
    public function rememberPassengerTrips(int $personId, Closure $callback): Collection
    {
        /** @var Collection $trips */
        $trips = $this->remember(
            array_merge($this->tripPersonTags($personId), ['reservations', 'persons']),
            $this->tripPassengerListKey($personId),
            self::TTL_LIST,
            $callback
        );

        return $trips;
    }

    /**
     * Forget cached trip.
     */
    public function forgetTrip(int $tripId): void
    {
        $this->forget($this->tripTags($tripId), $this->tripKey($tripId));
    }

    /**
     * Forget cached trip passengers.
     */
    public function forgetTripPassengers(int $tripId): void
    {
        $this->forget($this->tripPassengersTags($tripId), $this->tripPassengersKey($tripId));
    }

    /**
     * Forget cached trip search.
     */
    public function forgetTripSearch(): void
    {
        $this->flush($this->tripSearchTags());
    }

    /**
     * Forget cached trip lists for person.
     */
    public function forgetTripListsForPerson(int $personId): void
    {
        $this->forget(
            array_merge($this->tripPersonTags($personId), ['persons']),
            $this->tripDriverListKey($personId)
        );

        $this->forget(
            array_merge($this->tripPersonTags($personId), ['reservations', 'persons']),
            $this->tripPassengerListKey($personId)
        );
    }

    /**
     * Invalidate cached trip write.
     */
    public function invalidateTripWrite(int $tripId, int $driverId, ?int $oldDriverId = null): void
    {
        $this->forgetTrip($tripId);
        $this->forgetTripPassengers($tripId);
        $this->forgetTripSearch();
        $this->forgetTripListsForPerson($driverId);

        if ($oldDriverId !== null && $oldDriverId !== $driverId) {
            $this->forgetTripListsForPerson($oldDriverId);
        }
    }

    /**
     * Invalidate cached reservation write.
     */
    public function invalidateReservationWrite(int $tripId, int $passengerId, int $driverId): void
    {
        $this->forgetTripPassengers($tripId);
        $this->forgetTripListsForPerson($passengerId);
        $this->forgetTripListsForPerson($driverId);
        $this->forgetTripSearch();
        $this->forgetTrip($tripId);
    }

    /*
    |--------------------------------------------------------------------------
    | Types
    |--------------------------------------------------------------------------
    */

    /**
     * Get the cache tags for type.
     */
    public function typeTags(?int $id = null): array
    {
        return $id === null ? ['types'] : ['types', "type_id:$id"];
    }

    /**
     * Get the cache tags for type by value.
     */
    public function typeByValueTags(string $type): array
    {
        return ['types', 'type:'.$this->normalize($type)];
    }

    /**
     * Get the cache key for type.
     */
    public function typeKey(int $id): string
    {
        return "types:$id";
    }

    /**
     * Get the cache key for types all.
     */
    public function typesAllKey(): string
    {
        return 'types:all';
    }

    /**
     * Get the cache key for type by value.
     */
    public function typeByValueKey(string $type): string
    {
        return 'types:type:'.$this->normalize($type);
    }

    /**
     * Remember cached types all.
     */
    public function rememberTypesAll(Closure $callback): Collection
    {
        /** @var Collection $types */
        $types = $this->remember(
            $this->typeTags(),
            $this->typesAllKey(),
            self::TTL_LONG,
            $callback
        );

        return $types;
    }

    /**
     * Remember cached type by id.
     */
    public function rememberTypeById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->typeTags($id),
            $this->typeKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Remember cached type by value.
     */
    public function rememberTypeByValue(string $type, Closure $callback): mixed
    {
        return $this->remember(
            $this->typeByValueTags($type),
            $this->typeByValueKey($type),
            self::TTL_LONG,
            $callback
        );
    }

    /**
     * Store cached type.
     */
    public function putType(Model $type, string $value): void
    {
        $id = (int) $type->getKey();

        $this->put($this->typeTags($id), $this->typeKey($id), $type);
        $this->put($this->typeByValueTags($value), $this->typeByValueKey($value), $type);
    }

    /**
     * Forget cached type.
     */
    public function forgetType(int $id): void
    {
        $this->forget($this->typeTags($id), $this->typeKey($id));
    }

    /**
     * Forget cached type by value.
     */
    public function forgetTypeByValue(string $value): void
    {
        $this->forget($this->typeByValueTags($value), $this->typeByValueKey($value));
    }

    /**
     * Forget cached types all.
     */
    public function forgetTypesAll(): void
    {
        $this->forget($this->typeTags(), $this->typesAllKey());
    }

    /**
     * Normalize a cache key segment.
     */
    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
