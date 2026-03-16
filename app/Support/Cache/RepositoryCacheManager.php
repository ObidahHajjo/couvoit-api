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

class RepositoryCacheManager
{
    public const TTL_LONG = 360;
    public const TTL_LIST = 60;

    public function remember(array $tags, string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    public function put(array $tags, string $key, mixed $value, int $ttl = self::TTL_LONG): void
    {
        Cache::tags($tags)->put($key, $value, $ttl);
    }

    public function forget(array $tags, string $key): void
    {
        Cache::tags($tags)->forget($key);
    }

    public function flush(array $tags): void
    {
        Cache::tags($tags)->flush();
    }

    /*
    |--------------------------------------------------------------------------
    | Persons
    |--------------------------------------------------------------------------
    */

    public function personTags(?int $id = null): array
    {
        return $id === null ? ['persons'] : ['persons', "person:$id"];
    }

    public function personKey(int $id): string
    {
        return "persons:$id";
    }

    public function personsAllKey(): string
    {
        return 'persons:all';
    }

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

    public function putPerson(Person $person): void
    {
        $this->put(
            $this->personTags($person->id),
            $this->personKey($person->id),
            $person
        );
    }

    public function forgetPerson(int $id): void
    {
        $this->forget($this->personTags($id), $this->personKey($id));
    }

    public function forgetPersonsAll(): void
    {
        $this->forget($this->personTags(), $this->personsAllKey());
    }

    public function invalidatePersonListAndItem(int $id): void
    {
        $this->forgetPerson($id);
        $this->forgetPersonsAll();
    }

    public function invalidatePersonsByCarId(int $carId): void
    {
        $personIds = Person::query()
            ->where('car_id', $carId)
            ->pluck('id');
        foreach ($personIds as $personId) {
            $this->forgetPerson((int)$personId);
            Cache::tags($this->personTags((int)$personId))->flush();
        }
        $this->forgetPersonsAll();
    }

    /*
    |--------------------------------------------------------------------------
    | Cars
    |--------------------------------------------------------------------------
    */

    public function carTags(?int $id = null): array
    {
        return $id === null ? ['cars'] : ['cars', "car:$id"];
    }

    public function carKey(int $id): string
    {
        return "cars:$id";
    }

    public function carsAllKey(): string
    {
        return 'cars:all';
    }

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

    public function rememberCarById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->carTags($id),
            $this->carKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    public function putCar(Car $car): void
    {
        $this->put(
            $this->carTags($car->id),
            $this->carKey($car->id),
            $car
        );
    }

    public function forgetCar(int $id): void
    {
        $this->forget($this->carTags($id), $this->carKey($id));
    }

    public function forgetCarsAll(): void
    {
        $this->forget($this->carTags(), $this->carsAllKey());
    }

    public function invalidateCarListAndItem(int $id): void
    {
        $this->forgetCar($id);
        $this->forgetCarsAll();
        $this->invalidatePersonsByCarId($id);
    }

    public function invalidateCarsAndPersonsByModelId(int $modelId): void
    {
        $carIds = Car::query()
            ->where('model_id', $modelId)
            ->pluck('id');

        $this->invalidateCarsAndPersonsByCarIds($carIds->all());
    }

    public function invalidateCarsAndPersonsByColorId(int $colorId): void
    {
        $carIds = Car::query()
            ->where('color_id', $colorId)
            ->pluck('id');

        $this->invalidateCarsAndPersonsByCarIds($carIds->all());
    }

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
     * @param array<int,int|string> $carIds
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

    public function addressTags(int $id): array
    {
        return ['addresses', "address:$id"];
    }

    public function addressKey(int $id): string
    {
        return "addresses:$id";
    }

    public function rememberAddressById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->addressTags($id),
            $this->addressKey($id),
            self::TTL_LONG,
            $callback
        );
    }

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

    public function brandTags(?int $id = null): array
    {
        return $id === null ? ['brands'] : ['brands', "brand:$id"];
    }

    public function brandKey(int $id): string
    {
        return "brands:$id";
    }

    public function brandsAllKey(): string
    {
        return 'brands:all';
    }

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

    public function rememberBrandById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->brandTags($id),
            $this->brandKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    public function putBrand(Model $brand): void
    {
        $id = (int) $brand->getKey();

        $this->put($this->brandTags($id), $this->brandKey($id), $brand);
    }

    public function forgetBrand(int $id): void
    {
        $this->forget($this->brandTags($id), $this->brandKey($id));
    }

    public function forgetBrandsAll(): void
    {
        $this->forget($this->brandTags(), $this->brandsAllKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    */

    public function modelTags(?int $id = null): array
    {
        return $id === null ? ['models'] : ['models', "model:$id"];
    }

    public function modelByBrandTags(int $brandId): array
    {
        return ['models', "brand:$brandId"];
    }

    public function modelByNameTags(string $name): array
    {
        return ['models', 'name:' . $this->normalize($name)];
    }

    public function modelKey(int $id): string
    {
        return "models:$id";
    }

    public function modelsAllKey(): string
    {
        return 'models:all';
    }

    public function modelByBrandKey(int $brandId): string
    {
        return "models:brand:$brandId";
    }

    public function modelByNameKey(string $name): string
    {
        return 'models:name:' . $this->normalize($name);
    }

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

    public function rememberModelById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->modelTags($id),
            $this->modelKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    public function rememberModelByName(string $name, Closure $callback): mixed
    {
        return $this->remember(
            $this->modelByNameTags($name),
            $this->modelByNameKey($name),
            self::TTL_LONG,
            $callback
        );
    }

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

    public function putModel(CarModel $model): void
    {
        $this->put($this->modelTags($model->id), $this->modelKey($model->id), $model);
        $this->put($this->modelByNameTags($model->name), $this->modelByNameKey($model->name), $model);
    }

    public function forgetModel(int $id): void
    {
        $this->forget($this->modelTags($id), $this->modelKey($id));
    }

    public function forgetModelsAll(): void
    {
        $this->forget($this->modelTags(), $this->modelsAllKey());
    }

    public function forgetModelsByBrand(int $brandId): void
    {
        $this->forget($this->modelByBrandTags($brandId), $this->modelByBrandKey($brandId));
    }

    public function forgetModelByName(string $name): void
    {
        $this->forget($this->modelByNameTags($name), $this->modelByNameKey($name));
    }

    /*
    |--------------------------------------------------------------------------
    | Cities
    |--------------------------------------------------------------------------
    */

    public function cityTags(string $name, string $postalCode): array
    {
        return ['cities', 'city:' . $this->normalize($name) . ':' . trim($postalCode)];
    }

    public function cityKey(string $name, string $postalCode): string
    {
        return 'cities:' . $this->normalize($name) . ':' . trim($postalCode);
    }

    public function cityPostcodesTags(): array
    {
        return ['cities', 'cities:postcodes'];
    }

    public function cityPostcodesKey(): string
    {
        return 'cities:postcodes';
    }

    public function rememberCity(string $name, string $postalCode, Closure $callback): mixed
    {
        return $this->remember(
            $this->cityTags($name, $postalCode),
            $this->cityKey($name, $postalCode),
            self::TTL_LONG,
            $callback
        );
    }

    public function putCity(Model $city, string $name, string $postalCode): void
    {
        $this->put(
            $this->cityTags($name, $postalCode),
            $this->cityKey($name, $postalCode),
            $city
        );
    }

    public function forgetCity(string $name, string $postalCode): void
    {
        $this->forget(
            $this->cityTags($name, $postalCode),
            $this->cityKey($name, $postalCode)
        );
    }

    public function forgetCityPostcodes(): void
    {
        $this->forget($this->cityPostcodesTags(), $this->cityPostcodesKey());
    }

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

    public function colorTags(?int $id = null): array
    {
        return $id === null ? ['colors'] : ['colors', "color:$id"];
    }

    public function colorByHexTags(string $hex): array
    {
        return ['colors', 'hex:' . $this->normalize($hex)];
    }

    public function colorByNameTags(string $name): array
    {
        return ['colors', 'name:' . $this->normalize($name)];
    }

    public function colorKey(int $id): string
    {
        return "colors:$id";
    }

    public function colorsAllKey(): string
    {
        return 'colors:all';
    }

    public function colorByHexKey(string $hex): string
    {
        return 'colors:hex:' . $this->normalize($hex);
    }

    public function colorByNameKey(string $name): string
    {
        return 'colors:name:' . $this->normalize($name);
    }

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

    public function rememberColorById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->colorTags($id),
            $this->colorKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    public function rememberColorByName(string $name, Closure $callback): mixed
    {
        return $this->remember(
            $this->colorByNameTags($name),
            $this->colorByNameKey($name),
            self::TTL_LONG,
            $callback
        );
    }

    public function rememberColorByHex(string $hex, Closure $callback): mixed
    {
        return $this->remember(
            $this->colorByHexTags($hex),
            $this->colorByHexKey($hex),
            self::TTL_LONG,
            $callback
        );
    }

    public function putColor(Model $color, string $name, string $hex): void
    {
        $id = (int) $color->getKey();

        $this->put($this->colorTags($id), $this->colorKey($id), $color);
        $this->put($this->colorByNameTags($name), $this->colorByNameKey($name), $color);
        $this->put($this->colorByHexTags($hex), $this->colorByHexKey($hex), $color);
    }

    public function forgetColor(int $id): void
    {
        $this->forget($this->colorTags($id), $this->colorKey($id));
    }

    public function forgetColorByName(string $name): void
    {
        $this->forget($this->colorByNameTags($name), $this->colorByNameKey($name));
    }

    public function forgetColorByHex(string $hex): void
    {
        $this->forget($this->colorByHexTags($hex), $this->colorByHexKey($hex));
    }

    public function forgetColorsAll(): void
    {
        $this->forget($this->colorTags(), $this->colorsAllKey());
    }

    /*
    |--------------------------------------------------------------------------
    | Trips
    |--------------------------------------------------------------------------
    */

    public function tripTags(?int $id = null): array
    {
        return $id === null ? ['trips'] : ['trips', "trip:$id"];
    }

    public function tripSearchTags(): array
    {
        return ['trips', 'trips:search'];
    }

    public function tripPersonTags(int $personId): array
    {
        return ['trips', "person:$personId"];
    }

    public function tripPassengersTags(int $tripId): array
    {
        return ['trips', "trip:$tripId", 'reservations', 'persons'];
    }

    public function tripKey(int $id): string
    {
        return "trips:$id";
    }

    public function tripPassengersKey(int $id): string
    {
        return "trips:$id:passengers";
    }

    public function tripSearchKey(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int $perPage,
        int $page
    ): string {
        return sprintf(
            'trips:search:%s:%s:%s:per:%d:page:%d',
            $this->normalize($startingCity ?? 'any'),
            $this->normalize($arrivalCity ?? 'any'),
            $tripDate ?? 'any',
            $perPage,
            $page
        );
    }

    public function tripDriverListKey(int $personId): string
    {
        return "trips:driver:$personId";
    }

    public function tripPassengerListKey(int $personId): string
    {
        return "trips:passenger:$personId";
    }

    public function rememberTripById(int $id, Closure $callback): mixed
    {
        return $this->remember($this->tripTags($id), $this->tripKey($id), self::TTL_LONG, $callback);
    }

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

    public function rememberTripSearch(
        ?string $startingCity,
        ?string $arrivalCity,
        ?string $tripDate,
        int $perPage,
        int $page,
        Closure $callback
    ): LengthAwarePaginator {
        /** @var LengthAwarePaginator $paginator */
        $paginator = $this->remember(
            $this->tripSearchTags(),
            $this->tripSearchKey($startingCity, $arrivalCity, $tripDate, $perPage, $page),
            self::TTL_LONG,
            $callback
        );

        return $paginator;
    }

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

    public function forgetTrip(int $tripId): void
    {
        $this->forget($this->tripTags($tripId), $this->tripKey($tripId));
    }

    public function forgetTripPassengers(int $tripId): void
    {
        $this->forget($this->tripPassengersTags($tripId), $this->tripPassengersKey($tripId));
    }

    public function forgetTripSearch(): void
    {
        $this->flush($this->tripSearchTags());
    }

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

    public function typeTags(?int $id = null): array
    {
        return $id === null ? ['types'] : ['types', "type_id:$id"];
    }

    public function typeByValueTags(string $type): array
    {
        return ['types', 'type:' . $this->normalize($type)];
    }

    public function typeKey(int $id): string
    {
        return "types:$id";
    }

    public function typesAllKey(): string
    {
        return 'types:all';
    }

    public function typeByValueKey(string $type): string
    {
        return 'types:type:' . $this->normalize($type);
    }

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

    public function rememberTypeById(int $id, Closure $callback): mixed
    {
        return $this->remember(
            $this->typeTags($id),
            $this->typeKey($id),
            self::TTL_LONG,
            $callback
        );
    }

    public function rememberTypeByValue(string $type, Closure $callback): mixed
    {
        return $this->remember(
            $this->typeByValueTags($type),
            $this->typeByValueKey($type),
            self::TTL_LONG,
            $callback
        );
    }

    public function putType(Model $type, string $value): void
    {
        $id = (int) $type->getKey();

        $this->put($this->typeTags($id), $this->typeKey($id), $type);
        $this->put($this->typeByValueTags($value), $this->typeByValueKey($value), $type);
    }

    public function forgetType(int $id): void
    {
        $this->forget($this->typeTags($id), $this->typeKey($id));
    }

    public function forgetTypeByValue(string $value): void
    {
        $this->forget($this->typeByValueTags($value), $this->typeByValueKey($value));
    }

    public function forgetTypesAll(): void
    {
        $this->forget($this->typeTags(), $this->typesAllKey());
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
