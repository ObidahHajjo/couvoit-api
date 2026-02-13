<?php

namespace App\Http\Controllers;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Http\Requests\Car\StoreCarRequest;
use App\Http\Requests\Car\UpdateCarRequest;
use App\Models\Car;
use App\Models\Person;
use App\Services\Interfaces\CarServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use App\Http\Resources\CarResource;

class CarController extends Controller
{
    public function __construct(
        private readonly CarServiceInterface $cars
    )
    {
    }

    /**
     * GET /cars
     *
     * Admin: list all cars
     * User: only his car
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Car::class);

        /** @var Person $authPerson */
        $authPerson = auth()->user();

        if (!$authPerson->isAdmin()) {
            $authPerson->loadMissing(['car.model.brand', 'car.model.type', 'car.color']);
            $myCar = $authPerson->car;
            return CarResource::collection($myCar ? collect([$myCar]) : collect())
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        $cars = $this->cars->getCars();
        return CarResource::collection($cars)->response()->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /cars/{car}
     *
     * @param Car $car
     * @return JsonResponse
     */
    public function show(Car $car): JsonResponse
    {
        $this->authorize('view', $car);
        $car->loadMissing(['model.brand', 'model.type', 'color']);
        return (new CarResource($car))->response()->setStatusCode(Response::HTTP_OK);
    }

    /**
     * POST /cars
     *
     * Creates a car for the authenticated user.
     *
     * @param StoreCarRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    public function store(StoreCarRequest $request): JsonResponse
    {
        $this->authorize('create', Car::class);

        $person = auth()->user();

        $dto = CarCreateData::fromArray($request->validated());

        $car = $this->cars->createCar($dto, $person);

        $car->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($car))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PUT/PATCH /cars/{car}
     *
     * Updates a car (only editable fields).
     *
     * @param UpdateCarRequest $request
     * @param Car $car
     * @return JsonResponse
     * @throws Throwable
     */
    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $this->authorize('update', $car);

        $dto = CarUpdateData::fromArray($request->validated());
        $updatedCar = $this->cars->updateCar($car, $dto);

        $updatedCar->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($car))->response()->setStatusCode(Response::HTTP_OK);
    }

    /**
     * DELETE /cars/{car}
     *
     * @param Car $car
     * @return JsonResponse
     */
    public function destroy(Car $car): JsonResponse
    {
        $this->authorize('delete', $car);

        $this->cars->deleteCar($car);
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
