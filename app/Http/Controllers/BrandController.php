<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\Interfaces\BrandServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Brands', description: 'Brand endpoints.')]
/**
 * Handles brand lookup endpoints.
 */
class BrandController extends Controller
{
    /**
     * Create a new brand controller instance.
     */
    public function __construct(
        private readonly BrandServiceInterface $brands
    ) {}

    #[OA\Get(
        path: '/brands',
        operationId: 'brandsIndex',
        summary: 'List brands',
        security: [['bearerAuth' => []]],
        tags: ['Brands'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    /**
     * List brands.
     */
    public function index(): JsonResponse
    {
        $brands = $this->brands->getBrands();

        return BrandResource::collection($brands)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Get(
        path: '/brands/{id}',
        operationId: 'brandsShow',
        summary: 'Get brand by id',
        security: [['bearerAuth' => []]],
        tags: ['Brands'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    /**
     * Show a brand.
     */
    public function show(Brand $brand): JsonResponse
    {
        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
