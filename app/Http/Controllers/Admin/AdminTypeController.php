<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TypeResource;
use App\Models\Type;
use App\Services\Interfaces\AdminTypeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Types', description: 'Type management operations for administrators.')]
/**
 * Handles type management operations for administrators.
 */
class AdminTypeController extends Controller
{
    /**
     * Create a new admin type controller instance.
     */
    public function __construct(
        private readonly AdminTypeServiceInterface $types,
    ) {}

    #[OA\Get(
        path: '/admin/types',
        operationId: 'adminTypeList',
        summary: 'List all types',
        tags: ['Admin - Types'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * List all types.
     */
    public function index(): JsonResponse
    {
        $types = $this->types->listTypes();

        return TypeResource::collection($types)
            ->response()
            ->setStatusCode(200);
    }

    #[OA\Post(
        path: '/admin/types',
        operationId: 'adminTypeCreate',
        summary: 'Create a new type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', maxLength: 255),
                ]
            )
        ),
        tags: ['Admin - Types'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Create a new type.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:types,type',
        ]);

        $type = $this->types->createType($validated);

        return (new TypeResource($type))
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Put(
        path: '/admin/types/{type}',
        operationId: 'adminTypeUpdate',
        summary: 'Update an existing type',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', maxLength: 255),
                ]
            )
        ),
        tags: ['Admin - Types'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Type not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update an existing type.
     */
    public function update(Request $request, Type $type): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:types,type,'.$type->id,
        ]);

        $type = $this->types->updateType($type, $validated);

        return (new TypeResource($type))
            ->response()
            ->setStatusCode(200);
    }

    #[OA\Delete(
        path: '/admin/types/{type}',
        operationId: 'adminTypeDelete',
        summary: 'Delete a type',
        tags: ['Admin - Types'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Type not found'),
        ]
    )]
    /**
     * Delete a type.
     */
    public function destroy(Type $type): JsonResponse
    {
        $this->types->deleteType($type);

        return response()->json(['message' => 'Type deleted successfully.']);
    }
}
