<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\TypeResource;
use App\Models\Type;
use App\Services\Interfaces\AdminTypeServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminTypeController extends Controller
{
    public function __construct(
        private readonly AdminTypeServiceInterface $types,
    ) {}

    public function index(): JsonResponse
    {
        $types = $this->types->listTypes();

        return TypeResource::collection($types)
            ->response()
            ->setStatusCode(200);
    }

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

    public function update(Request $request, Type $type): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|max:255|unique:types,type,' . $type->id,
        ]);

        $type = $this->types->updateType($type, $validated);

        return (new TypeResource($type))
            ->response()
            ->setStatusCode(200);
    }

    public function destroy(Type $type): JsonResponse
    {
        $this->types->deleteType($type);

        return response()->json(['message' => 'Type deleted successfully.']);
    }
}
