<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Services\Interfaces\AdminCarModelServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Car Models', description: 'Car model management operations for administrators.')]
/**
 * Handles car model management operations for administrators.
 */
class AdminCarModelController extends Controller
{
    /**
     * Create a new admin car model controller instance.
     */
    public function __construct(
        private readonly AdminCarModelServiceInterface $models,
    ) {}

    #[OA\Get(
        path: '/admin/car-models',
        operationId: 'adminCarModelList',
        summary: 'List all car models',
        tags: ['Admin - Car Models'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * List all car models.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $page = (int) $request->query('page', 1);

        $models = $this->models->listModels($perPage);

        return response()->json($models);
    }

    #[OA\Post(
        path: '/admin/car-models',
        operationId: 'adminCarModelCreate',
        summary: 'Create a new car model',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'brand_id', 'type_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'brand_id', type: 'integer'),
                    new OA\Property(property: 'type_id', type: 'integer'),
                ]
            )
        ),
        tags: ['Admin - Car Models'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Create a new car model.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'type_id' => 'required|exists:types,id',
        ]);

        $model = $this->models->createModel($validated);

        return response()->json($model, 201);
    }

    #[OA\Put(
        path: '/admin/car-models/{model}',
        operationId: 'adminCarModelUpdate',
        summary: 'Update an existing car model',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'brand_id', 'type_id'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                    new OA\Property(property: 'brand_id', type: 'integer'),
                    new OA\Property(property: 'type_id', type: 'integer'),
                ]
            )
        ),
        tags: ['Admin - Car Models'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Car model not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update an existing car model.
     */
    public function update(Request $request, CarModel $model): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'type_id' => 'required|exists:types,id',
        ]);

        $model = $this->models->updateModel($model, $validated);

        return response()->json($model);
    }

    #[OA\Delete(
        path: '/admin/car-models/{model}',
        operationId: 'adminCarModelDelete',
        summary: 'Delete a car model',
        tags: ['Admin - Car Models'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Car model not found'),
        ]
    )]
    /**
     * Delete a car model.
     */
    public function destroy(CarModel $model): JsonResponse
    {
        $this->models->deleteModel($model);

        return response()->json(['message' => 'Model deleted successfully.']);
    }
}
