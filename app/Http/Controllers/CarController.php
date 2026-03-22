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
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[OA\Tag(name: 'Cars', description: 'Car endpoints. Admin can list all; user only sees/edits their own car.')]
/**
 * HTTP controller for Car endpoints.
 */
class CarController extends Controller
{
    /**
     * Create a new car controller instance.
     */
    public function __construct(
        private readonly CarServiceInterface $cars,
    ) {
        $this->authorizeResource(Car::class, 'car');
    }

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
    /**
     * List cars.
     *
     * Admin users receive the full collection; other users only receive their own car.
     */
    public function index(): JsonResponse
    {
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
    /**
     * Show a single car.
     */
    public function show(Car $car): JsonResponse
    {
        $car->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($car))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

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
    /**
     * Create a car for the authenticated user.
     *
     * @throws Throwable Propagates service-layer failures.
     */
    public function store(StoreCarRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $person = $user->person;

        $dto = CarCreateData::fromArray($request->validated());
        $car = $this->cars->createCar($dto, $person);

        $car->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($car))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

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
    /**
     * Update a car.
     *
     * @throws Throwable Propagates service-layer failures.
     */
    public function update(UpdateCarRequest $request, Car $car): JsonResponse
    {
        $dto = CarUpdateData::fromArray($request->validated());
        $updatedCar = $this->cars->updateCar($car, $dto);

        $updatedCar->loadMissing(['model.brand', 'model.type', 'color']);

        return (new CarResource($updatedCar))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

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
    /**
     * Delete a car.
     */
    public function destroy(Car $car): Response
    {
        $this->cars->deleteCar($car);

        return response()->noContent();
    }

    #[OA\Delete(
        path: '/cars/search',
        operationId: 'carsSearch',
        summary: 'Search a car',
        security: [['bearerAuth' => []]],
        tags: ['Cars'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'brand', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    /**
     * Search cars by free-text query and brand prefix.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        $brand = trim((string) $request->query('brand', ''));

        if (mb_strlen($query) < 2 || mb_strlen($brand) < 3) {
            return response()->json([
                'data' => [],
            ]);
        }

        $results = $this->cars->search(
            $query,
            $brand
        );

        return response()->json([
            'data' => $results,
        ]);
    }
}
