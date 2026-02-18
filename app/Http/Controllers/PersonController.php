<?php

namespace App\Http\Controllers;

use App\Http\Requests\Person\StorePersonRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use App\Http\Requests\Person\UpdateRolePersonRequest;
use App\Http\Resources\PersonResource;
use App\Http\Resources\TripResource;
use App\Models\Person;
use App\Services\Interfaces\PersonServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PersonController extends Controller
{
    public function __construct(
        private readonly PersonServiceInterface $persons
    )
    {
    }

    /**
     * GET /persons (Admin only)
     */
    public function index(): JsonResponse
    {
        logger()->info('AUTH DEBUG', [
            'id' => auth()->id(),
            'role_id' => auth()->user()?->role_id,
            'is_admin' => auth()->user()?->isAdmin(),
        ]);

        $this->authorize('viewAny', Person::class);

        $people = $this->persons->list();

        return PersonResource::collection($people)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /persons/{id}
     */
    public function show(Person $person): JsonResponse
    {
        $this->authorize('view', $person);
        return PersonResource::make($person)->response()->setStatusCode(Response::HTTP_OK);
    }


    /**
     * GET /persons/{id}/trips-driver
     */
    public function tripsDriver(Person $person): JsonResponse
    {
        $this->authorize('viewTripsDriver', $person);

        $trips = $this->persons->tripsAsDriver($person);

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /persons/{id}/trips-passenger
     */
    public function tripsPassenger(Person $person): JsonResponse
    {
        $this->authorize('viewTripsPassenger', $person);

        $trips = $this->persons->tripsAsPassenger($person);

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * POST /persons (User only for self, Admin allowed)
     * In practice: this should "complete profile" (update current user),
     * not create a new person row unless your business rules say otherwise.
     */
    public function store(StorePersonRequest $request): JsonResponse
    {
        $this->authorize('create', Person::class);

        /** @var Person $me */
        $me = auth()->user();

        // Treat "store" as "complete my profile"
        $updated = $this->persons->update($me, $request->validated());

        $updated->loadMissing(['role', 'car']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * PATCH /persons/{id}
     */
    public function update(UpdatePersonRequest $request, Person $person): JsonResponse
    {
        $this->authorize('update', $person);

        $updated = $this->persons->update($person, $request->validated());
        $updated->loadMissing(['role', 'car']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * DELETE /persons/{id} (Admin only per your table)
     */
    public function destroy(Person $person): Response
    {
        $this->authorize('delete', $person);

        $this->persons->softDelete($person);

        return response()->noContent();
    }

    public function updateRole(UpdateRolePersonRequest $request) : JsonResponse
    {
        $this->authorize('updateRole', Person::class);
        $data = $request->validated();
        $updated = $this->persons->updateRole($data['person_id'], $data['role_id']);
        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
