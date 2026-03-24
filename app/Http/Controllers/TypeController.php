<?php

namespace App\Http\Controllers;

use App\Http\Resources\TypeResource;
use App\Repositories\Interfaces\TypeRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class TypeController extends Controller
{
    public function __construct(
        private readonly TypeRepositoryInterface $types,
    ) {}

    public function index(): JsonResponse
    {
        $types = $this->types->all();

        return TypeResource::collection($types)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
