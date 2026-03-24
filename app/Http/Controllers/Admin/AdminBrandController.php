<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\Interfaces\AdminBrandServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminBrandController extends Controller
{
    public function __construct(
        private readonly AdminBrandServiceInterface $brands,
    ) {}

    public function index(): JsonResponse
    {
        $brands = $this->brands->listBrands();

        return response()->json($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:brands,name|max:255'
        ]);

        $brand = $this->brands->createBrand($validated);

        return response()->json($brand, 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:brands,name,' . $brand->id . '|max:255'
        ]);

        $brand = $this->brands->updateBrand($brand, $validated);

        return response()->json($brand);
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $this->brands->deleteBrand($brand);

        return response()->json(['message' => 'Brand deleted successfully.']);
    }
}
