<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Interfaces\AdminBrandServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Brands', description: 'Brand management operations for administrators.')]
/**
 * Handles brand management operations for administrators.
 */
class AdminBrandController extends Controller
{
    /**
     * Create a new admin brand controller instance.
     */
    public function __construct(
        private readonly AdminBrandServiceInterface $brands,
    ) {}

    #[OA\Get(
        path: '/admin/brands',
        operationId: 'adminBrandList',
        summary: 'List all brands',
        tags: ['Admin - Brands'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * List all brands.
     */
    public function index(): JsonResponse
    {
        $brands = $this->brands->listBrands();

        return response()->json($brands);
    }

    #[OA\Post(
        path: '/admin/brands',
        operationId: 'adminBrandCreate',
        summary: 'Create a new brand',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                ]
            )
        ),
        tags: ['Admin - Brands'],
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Create a new brand.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:brands,name|max:255'
        ]);

        $brand = $this->brands->createBrand($validated);

        return response()->json($brand, 201);
    }

    #[OA\Put(
        path: '/admin/brands/{brand}',
        operationId: 'adminBrandUpdate',
        summary: 'Update an existing brand',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255),
                ]
            )
        ),
        tags: ['Admin - Brands'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Brand not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update an existing brand.
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:brands,name,' . $brand->id . '|max:255'
        ]);

        $brand = $this->brands->updateBrand($brand, $validated);

        return response()->json($brand);
    }

    #[OA\Delete(
        path: '/admin/brands/{brand}',
        operationId: 'adminBrandDelete',
        summary: 'Delete a brand',
        tags: ['Admin - Brands'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Brand not found'),
        ]
    )]
    /**
     * Delete a brand.
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $this->brands->deleteBrand($brand);

        return response()->json(['message' => 'Brand deleted successfully.']);
    }
}
