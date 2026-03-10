<?php

namespace App\Http\Controllers;

use App\DTOS\Car\CarCreateData;
use App\DTOS\Car\CarUpdateData;
use App\Http\Requests\Car\StoreCarRequest;
use App\Http\Requests\Car\UpdateCarRequest;
use App\Http\Resources\CarResource;
use App\Models\Car;
use App\Models\User;
use App\Services\Interfaces\CarServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use OpenApi\Attributes as OA;

/**
 * HTTP controller for Car endpoints.
 */
#[OA\Tag(name: 'Cars', description: 'Car endpoints. Admin can list all; user only sees/edits their own car.')]
class CarController extends Controller
{
    /**
     * @param CarServiceInterface $cars
     */
    public function __construct(
        private readonly CarServiceInterface $cars
    ) {}

    /**
     * List cars.
     * Admin: list all cars
     * User: only his car
     *
     * @return JsonResponse
     */
    #[OA\Get(
        path: '/cars',
        operationId: 'carsIndex',
        summary: 'List cars',
        security: [['bearerAuth' => []]],
        tags: ['Cars'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Car::class);

        /** @var User $authUser */
        $authUser = auth()->user();

        if (! $authUser->isAdmin()) {
            $authPerson = $authUser->person;
            $authPerson->loadMissing(['car.model.brand', 'car.model.type', 'car.color']);
            $myCar = $authPerson->car;

            return CarResource::collection($myCar ? collect([$myCar]) : collect())
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        }

        $cars = $this->cars->getCars();

        return CarResource::collection($cars)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Show a car.
     *
     * @param Car $car
     * @return JsonResponse
     */
    #[OA\Get(
        path: '/cars/{id}',
        operationId: 'carsShow',
        summary: 'Get car by id',
        security: [['bearerAuth' => []]],
        tags: ['Cars'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show(Car $car): JsonResponse
    {
        $person = auth()->user()->person;
        $this->authorize('view', [Car::class,$person,$car]);
        $car->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($car))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Create a car for the authenticated user.
     *
     * @param StoreCarRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    #[OA\Post(
        path: '/cars',
        operationId: 'carsStore',
        summary: 'Create my car',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreCarRequestPayload')
        ),
        tags: ['Cars'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreCarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $person = $user->person;
        $this->authorize('create', [Car::class, $person]);

        $dto = CarCreateData::fromArray($request->validated());
        $car = $this->cars->createCar($dto, $person);

        $car->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($car))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a car.
     *
     * @param UpdateCarRequest $request
     * @param Car $car
     * @return JsonResponse
     * @throws Throwable
     */
    #[OA\Patch(
        path: '/cars/{id}',
        operationId: 'carsUpdate',
        summary: 'Update my car',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateCarRequestPayload')
        ),
        tags: ['Cars'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $this->authorize('update', [Car::class,$car]);

        $dto = CarUpdateData::fromArray($request->validated());
        $updatedCar = $this->cars->updateCar($car, $dto);

        $updatedCar->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($updatedCar))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Delete a car.
     *
     * @param Car $car
     * @return Response
     */
    #[OA\Delete(
        path: '/cars/{id}',
        operationId: 'carsDestroy',
        summary: 'Delete my car',
        security: [['bearerAuth' => []]],
        tags: ['Cars'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroy(Car $car): Response
    {
        $this->authorize('delete', [Car::class,$car]);

        $this->cars->deleteCar($car);

        return response()->noContent();
    }
}
