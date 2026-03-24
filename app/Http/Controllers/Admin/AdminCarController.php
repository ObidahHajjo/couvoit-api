<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Services\Interfaces\AdminCarServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminCarController extends Controller
{
    public function __construct(
        private readonly AdminCarServiceInterface $cars,
    ) {}

    public function index(): JsonResponse
    {
        $cars = $this->cars->listCars();

        return response()->json($cars);
    }

    public function destroy(Car $car): JsonResponse
    {
        $this->cars->deleteCar($car);

        return response()->json(['message' => 'Car deleted successfully.']);
    }
}
