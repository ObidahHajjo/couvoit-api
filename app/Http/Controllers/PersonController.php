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
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(name: 'Persons', description: 'Person endpoints (admin listing, profile, trips).')]
class PersonController extends Controller
{
    public function __construct(
        private readonly PersonServiceInterface $persons
    ) {}

    #[OA\Get(
        path: '/api/persons',
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
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Person::class);

        $people = $this->persons->list();

        return PersonResource::collection($people)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/api/persons/{id}',
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
    public function show(Person $person): JsonResponse
    {
        $this->authorize('view', $person);

        return PersonResource::make($person)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/api/persons/{id}/trips-driver',
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
    public function tripsDriver(Person $person): JsonResponse
    {
        $this->authorize('viewTripsDriver', $person);

        $trips = $this->persons->tripsAsDriver($person);

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/api/persons/{id}/trips-passenger',
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
    public function tripsPassenger(Person $person): JsonResponse
    {
        $this->authorize('viewTripsPassenger', $person);

        $trips = $this->persons->tripsAsPassenger($person);

        return TripResource::collection($trips)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Complete my profile.
     *
     * Now: authenticated user is User, profile is User->person.
     *
     * @throws Throwable
     */
    #[OA\Post(
        path: '/api/persons',
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
    public function store(StorePersonRequest $request): JsonResponse
    {
        $this->authorize('create', Person::class);

        /** @var User $user */
        $user = auth()->user();

        $person = $user->person;
        if (!$person) {
            // If you want profile created lazily:
            $person = $this->persons->createForUser($user, []);
        }

        $updated = $this->persons->update($person, $request->validated());
        $updated->loadMissing(['car', 'user.role']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @throws Throwable
     */
    #[OA\Patch(
        path: '/api/persons/{id}',
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
    public function update(UpdatePersonRequest $request, Person $person): JsonResponse
    {
        $this->authorize('update', $person);

        $updated = $this->persons->update($person, $request->validated());
        $updated->loadMissing(['car', 'user.role']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Delete(
        path: '/api/persons/{id}',
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
    public function destroy(Person $person): Response
    {
        $this->authorize('delete', $person);

        $this->persons->softDelete($person);

        return response()->noContent();
    }

    #[OA\Patch(
        path: '/api/admin/person-role',
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
    public function updateRole(UpdateRolePersonRequest $request): JsonResponse
    {
        // Policy still targets Person::class; inside policy check auth()->user()->isAdmin()
        $this->authorize('updateRole', Person::class);

        $data = $request->validated();

        // NOTE: This now updates the USER role (auth), not the person role.
        $updated = $this->persons->updateUserRoleByPersonId((int) $data['person_id'], (int) $data['role_id']);

        return PersonResource::make($updated)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
