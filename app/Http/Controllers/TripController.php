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
use App\Models\Person;
use App\Models\Trip;
use App\Services\Interfaces\TripServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TripController extends Controller
{
    public function __construct(
        private readonly TripServiceInterface $trips,
    ) {}

    /**
     * GET /trips
     * Query: startingcity, arrivalcity, tripdate, per_page
     */
    public function index(TripIndexRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Trip::class);

        $starting = $request->validated('startingcity');
        $arrival  = $request->validated('arrivalcity');
        $date     = $request->validated('tripdate');
        $perPage  = (int) ($request->validated('per_page') ?? 15);

        $paginator = $this->trips->searchTrips($starting, $arrival, $date, $perPage);

        // Return resources while keeping pagination meta
        return TripResource::collection($paginator)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /trips/{id}
     */
    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        // Ensure relations exist for resource
        $trip->loadMissing(['driver', 'departureAddress.city', 'arrivalAddress.city']);

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /trips/{id}/person
     * List passengers of a trip
     */
    public function passengers(Trip $trip): JsonResponse
    {
        $this->authorize('viewPassengers', $trip);

        $passengers = $this->trips->getTripPassengers($trip);

        return PersonResource::collection($passengers)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * POST /trips
     */
    public function store(StoreTripRequest $request): JsonResponse
    {
        /** @var Person $authPerson */
        $authPerson = auth()->user();

        // Spec: user can create only if "driver" (has car). Admin ok.
        // If person_id is present and not self => policy createFor should enforce admin
        $driverId = $request->safe()->input('person_id');

        if ($driverId && (int) $driverId !== (int) $authPerson->id) {
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
     * PATCH /trips/{id}
     */
    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        /** @var Person $authPerson */
        $authPerson = auth()->user();

        $this->authorize('update', $trip);

        $trip = $this->trips->updateTrip($trip, $request->validated(), $authPerson);

        $trip->loadMissing(['driver', 'departureAddress.city', 'arrivalAddress.city']);

        return (new TripResource($trip))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * DELETE /trips/{trip}
     */
    public function destroy(Trip $trip): Response
    {
        /** @var Person $authPerson */
        $authPerson = auth()->user();

        $this->authorize('forceDelete', $trip);

        $this->trips->deleteTripPermanently($trip, $authPerson);

        return response()->noContent();
    }

    /**
     * PATCH /trips/{trip}/cancel
     */
    public function cancel(Trip $trip): Response
    {
        /** @var Person $authPerson */
        $authPerson = auth()->user();

        $this->authorize('cancel', $trip);

        $this->trips->cancelTrip($trip, $authPerson);

        return response()->noContent();
    }

    /**
     * DELETE /trips/{trip}/reservations
     */
    public function cancelReservation(Trip $trip, CancelReservationRequest $request): Response
    {
        /** @var Person $authPerson */
        $authPerson = auth()->user();

        $this->authorize('cancelReservation', $trip);

        if ($authPerson->isAdmin() && $request->validated('person_id') === null) {
            throw new ValidationLogicException('person_id is required for admin.');
        }

        $personId = $authPerson->isAdmin()
            ? (int) $request->validated('person_id')
            : (int) $authPerson->id;

        $this->trips->cancelReservation($trip, $personId, $authPerson);

        return response()->noContent();
    }

    /**
     * POST /trips/{id}/person
     * Reserve a seat for a trip
     */
    public function reserve(ReserveTripRequest $request, Trip $trip): JsonResponse
    {
        /** @var Person $authPerson */
        $authPerson = auth()->user();

        // Spec: user reserves for self; admin can reserve for someone else
        $personId = $authPerson->isAdmin()
            ? (int) $request->validated('person_id')
            : (int) $authPerson->id;

        $passenger = $this->trips->getPersonById($personId);

        $this->authorize('reserve', [$trip, $passenger]);

        $created = $this->trips->reserveSeat($trip, $personId, $authPerson);

        return response()->json([
            'status' => $created ? 'CREATED' : 'ALREADY_EXISTS',
        ], $created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
