<?php

namespace App\Http\Controllers;

use App\Http\Requests\Person\StorePersonRequest;
use App\Http\Requests\Person\UpdatePersonRequest;
use App\Http\Requests\Person\UpdateRolePersonRequest;
use App\Http\Resources\PersonResource;
use App\Http\Resources\TripResource;
use App\Models\Person;
use App\Models\User;
use App\Services\Interfaces\PersonServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[OA\Tag(name: 'Persons', description: 'Person endpoints (admin listing, profile, trips).')]
/**
 * Handles person profile endpoints.
 */
class PersonController extends Controller
{
    /**
     * Create a new person controller instance.
     */
    public function __construct(
        private readonly PersonServiceInterface $persons
    ) {
        $this->authorizeResource(Person::class, 'person');
    }

    #[OA\Get(
        path: '/persons',
        operationId: 'personsIndex',
        summary: 'List persons (admin)',
        security: [['bearerAuth' => []]],
        tags: ['Persons'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
        ]
    )]
    /**
     * List persons.
     */
    public function index(): JsonResponse
    {
        $people = $this->persons->list();

        return PersonResource::collection($people)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/persons/{id}',
        operationId: 'personsShow',
        summary: 'Get person by id',
        security: [['bearerAuth' => []]],
        tags: ['Persons'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    /**
     * Show a single person profile.
     */
    public function show(Person $person): JsonResponse
    {
        return PersonResource::make($person)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/persons/{id}/trips-driver',
        operationId: 'personsTripsDriver',
        summary: 'Trips as driver',
        security: [['bearerAuth' => []]],
        tags: ['Persons'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    /**
     * List trips driven by the given person.
     */
    public function tripsDriver(Person $person): JsonResponse
    {
        $this->authorize('viewTripsDriver', [Person::class, $person]);

        $trips = $this->persons->tripsAsDriver($person);

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/persons/{id}/trips-passenger',
        operationId: 'personsTripsPassenger',
        summary: 'Trips as passenger',
        security: [['bearerAuth' => []]],
        tags: ['Persons'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    /**
     * List trips reserved by the given person.
     */
    public function tripsPassenger(Person $person): JsonResponse
    {
        $this->authorize('viewTripsPassenger', [Person::class, $person]);

        $trips = $this->persons->tripsAsPassenger($person);

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/persons',
        operationId: 'personsStore',
        summary: 'Complete my profile',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/StorePersonRequestPayload')),
        tags: ['Persons'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Complete or initialize the authenticated user's profile.
     *
     * @throws Throwable Propagates service-layer failures.
     */
    public function store(StorePersonRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $person = $user->person;
        if (! $person) {
            $person = $this->persons->createForUser($user, []);
        }

        $updated = $this->persons->update($person, $request->validated());
        $updated->loadMissing(['car', 'user.role']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Patch(
        path: '/persons/{id}',
        operationId: 'personsUpdate',
        summary: 'Update person',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdatePersonRequestPayload')),
        tags: ['Persons'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update an existing person profile.
     *
     * @throws Throwable Propagates service-layer failures.
     */
    public function update(UpdatePersonRequest $request, Person $person): JsonResponse
    {
        $updated = $this->persons->update($person, $request->validated());
        $updated->loadMissing(['car', 'user.role']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Delete(
        path: '/persons/{id}',
        operationId: 'personsDestroy',
        summary: 'Delete person (soft)',
        security: [['bearerAuth' => []]],
        tags: ['Persons'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 204, description: 'No Content'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    /**
     * Soft delete a person profile.
     */
    public function destroy(Person $person): Response
    {
        $this->persons->softDelete($person);

        return response()->noContent();
    }

    #[OA\Patch(
        path: '/admin/person-role',
        operationId: 'personsUpdateRole',
        summary: 'Update user role (admin)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/UpdateRolePersonRequestPayload')),
        tags: ['Persons'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update the role attached to a person account.
     */
    public function updateRole(UpdateRolePersonRequest $request): JsonResponse
    {
        $this->authorize('updateRole', Person::class);

        $data = $request->validated();

        $updated = $this->persons->updateUserRoleByPersonId((int) $data['person_id'], (int) $data['role_id']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
