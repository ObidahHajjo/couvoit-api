<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarModel;
use App\Services\Interfaces\AdminCarModelServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminCarModelController extends Controller
{
    public function __construct(
        private readonly AdminCarModelServiceInterface $models,
    ) {}

    public function index(): JsonResponse
    {
        $models = $this->models->listModels();

        return response()->json($models);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'type_id' => 'required|exists:types,id'
        ]);

        $model = $this->models->createModel($validated);

        return response()->json($model, 201);
    }

    public function update(Request $request, CarModel $model): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_id' => 'required|exists:brands,id',
            'type_id' => 'required|exists:types,id'
        ]);

        $model = $this->models->updateModel($model, $validated);

        return response()->json($model);
    }

    public function destroy(CarModel $model): JsonResponse
    {
        $this->models->deleteModel($model);

        return response()->json(['message' => 'Model deleted successfully.']);
    }
}
