<?php

namespace App\Http\Controllers;

use App\Http\Resources\TypeResource;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Types', description: 'Vehicle type endpoints')]
/**
 * Handles vehicle type endpoints.
 */
class TypeController extends Controller
{
    /**
     * Create a new type controller instance.
     *
     * @param  TypeRepositoryInterface  $types  Repository for accessing vehicle types.
     */
    public function __construct(
        private readonly TypeRepositoryInterface $types,
    ) {}

    #[OA\Get(
        path: '/types',
        operationId: 'listTypes',
        summary: 'List vehicle types',
        tags: ['Types'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/TypeResource'))),
        ]
    )]
    /**
     * List all available vehicle types.
     */
    public function index(): JsonResponse
    {
        $types = $this->types->all();

        return TypeResource::collection($types)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
