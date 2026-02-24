<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidationLogicException;
use App\Http\Requests\Trip\CancelReservationRequest;
use App\Http\Requests\Trip\ReserveTripRequest;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\TripIndexRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Resources\PersonResource;
use App\Http\Resources\TripResource;
use App\Models\Trip;
use App\Models\User;
use App\Services\Interfaces\TripServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * HTTP controller for Trip endpoints.
 */
#[OA\Tag(name: 'Trips', description: 'Trip endpoints (search, create, update, cancel, reserve).')]
class TripController extends Controller
{
    /**
     * @param TripServiceInterface $trips
     */
    public function __construct(
        private readonly TripServiceInterface $trips,
    ) {}

    /**
     * Search trips.
     *
     * Query: startingcity, arrivalcity, tripdate, per_page
     *
     * @param TripIndexRequest $request
     * @return JsonResponse
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/trips',
        operationId: 'tripsIndex',
        summary: 'Search trips',
        security: [['bearerAuth' => []]],
        tags: ['Trips'],
        parameters: [
            new OA\Parameter(name: 'startingcity', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'arrivalcity', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tripdate', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(TripIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Trip::class);

        $starting = $request->validated('startingcity');
        $arrival  = $request->validated('arrivalcity');
        $date     = $request->validated('tripdate');
        $perPage  = (int) ($request->validated('per_page') ?? 15);

        $paginator = $this->trips->searchTrips($starting, $arrival, $date, $perPage);

        return TripResource::collection($paginator)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Show a trip.
     *
     * @param Trip $trip
     * @return JsonResponse
     */
    #[OA\Get(
        path: '/api/trips/{id}',
        operationId: 'tripsShow',
        summary: 'Get trip by id',
        security: [['bearerAuth' => []]],
        tags: ['Trips'],
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
    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', [Trip::class,$trip]);

        $trip->loadMissing(['driver', 'departureAddress.city', 'arrivalAddress.city']);

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * List passengers of a trip.
     *
     * @param Trip $trip
     * @return JsonResponse
     *
     * @throws Throwable
     */
    #[OA\Get(
        path: '/api/trips/{id}/person',
        operationId: 'tripsPassengers',
        summary: 'List trip passengers',
        security: [['bearerAuth' => []]],
        tags: ['Trips'],
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
    public function passengers(Trip $trip): JsonResponse
    {
        $this->authorize('viewPassengers', [Trip::class,$trip]);

        $passengers = $this->trips->getTripPassengers($trip);

        return PersonResource::collection($passengers)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Create a trip.
     *
     * @param StoreTripRequest $request
     * @return JsonResponse
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/trips',
        operationId: 'tripsStore',
        summary: 'Create trip',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/StoreTripRequestPayload')
        ),
        tags: ['Trips'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreTripRequest $request): JsonResponse
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $authPerson = $authUser->person;

        $driverId = $request->safe()->input('person_id');

        if ($driverId && (int) $driverId !== $authPerson->id) {
            $driver = $this->trips->getPersonById((int) $driverId);
            $this->authorize('createFor', [Trip::class, $driver]);
        } else {
            $this->authorize('create', Trip::class);
        }

        $trip = $this->trips->createTrip($request->validated(), $authPerson);

        $trip->loadMissing(['driver', 'departureAddress.city', 'arrivalAddress.city']);

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Update a trip.
     *
     * @param UpdateTripRequest $request
     * @param Trip $trip
     * @return JsonResponse
     * @throws Throwable
     */
    #[OA\Patch(
        path: '/api/trips/{id}',
        operationId: 'tripsUpdate',
        summary: 'Update trip',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/UpdateTripRequestPayload')
        ),
        tags: ['Trips'],
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
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $authPerson = $authUser->person;

        $this->authorize('update', [Trip::class,$trip]);

        $trip = $this->trips->updateTrip($trip, $request->validated(), $authPerson);

        $trip->loadMissing(['driver', 'departureAddress.city', 'arrivalAddress.city']);

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Permanently delete a trip.
     *
     * @param Trip $trip
     * @return Response
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/api/trips/{id}',
        operationId: 'tripsDestroy',
        summary: 'Delete trip permanently',
        security: [['bearerAuth' => []]],
        tags: ['Trips'],
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
    public function destroy(Trip $trip): Response
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $authPerson = $authUser->person;

        $this->authorize('delete', [Trip::class,$trip]);

        $this->trips->deleteTripPermanently($trip, $authPerson);

        return response()->noContent();
    }

    /**
     * Cancel a trip.
     *
     * @param Trip $trip
     * @return Response
     * @throws Throwable
     */
    #[OA\Patch(
        path: '/api/trips/{id}/cancel',
        operationId: 'tripsCancel',
        summary: 'Cancel trip',
        security: [['bearerAuth' => []]],
        tags: ['Trips'],
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
    public function cancel(Trip $trip): Response
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $authPerson = $authUser->person;

        $this->authorize('cancel', [Trip::class,$trip]);

        $this->trips->cancelTrip($trip, $authPerson);

        return response()->noContent();
    }

    /**
     * Cancel a reservation for this trip.
     *
     * @param Trip $trip
     * @param CancelReservationRequest $request
     * @return Response
     * @throws Throwable
     */
    #[OA\Delete(
        path: '/api/trips/{id}/reservations',
        operationId: 'tripsCancelReservation',
        summary: 'Cancel reservation',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: '#/components/schemas/CancelReservationRequestPayload')
        ),
        tags: ['Trips'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function cancelReservation(Trip $trip, CancelReservationRequest $request): Response
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $authPerson = $authUser->person;

        $this->authorize('cancelReservation', [Trip::class,$trip]);

        if ($authUser->isAdmin() && $request->validated('person_id') === null) {
            throw new ValidationLogicException('person_id is required for admin.');
        }

        $personId = $authUser->isAdmin()
            ? (int) $request->validated('person_id')
            : $authPerson->id;

        $this->trips->cancelReservation($trip, $personId, $authPerson);

        return response()->noContent();
    }

    /**
     * Reserve a seat for a trip.
     *
     * @param ReserveTripRequest $request
     * @param Trip $trip
     * @return JsonResponse
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/trips/{id}/person',
        operationId: 'tripsReserve',
        summary: 'Reserve a seat',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ReserveTripRequestPayload')
        ),
        tags: ['Trips'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 200, description: 'Already exists'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function reserve(ReserveTripRequest $request, Trip $trip): JsonResponse
    {
        /** @var User $authUser */
        $authUser = auth()->user();
        $authPerson = $authUser->person;

        $personId = $authUser->isAdmin()
            ? (int) $request->validated('person_id')
            : $authPerson->id;

        Log::info("person id");
        $passenger = $this->trips->getPersonById($personId);

        $this->authorize('reserve', [Trip::class,$trip, $passenger]);

        $created = $this->trips->reserveSeat($trip, $personId, $authPerson);

        return response()->json([
            'status' => $created ? 'CREATED' : 'ALREADY_EXISTS',
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
