<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Services\Interfaces\AdminCarServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Cars', description: 'Car management operations for administrators.')]
/**
 * Handles car management operations for administrators.
 */
class AdminCarController extends Controller
{
    /**
     * Create a new admin car controller instance.
     */
    public function __construct(
        private readonly AdminCarServiceInterface $cars,
    ) {}

    #[OA\Get(
        path: '/admin/cars',
        operationId: 'adminCarList',
        summary: 'List all cars',
        tags: ['Admin - Cars'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * List all cars.
     */
    public function index(): JsonResponse
    {
        $cars = $this->cars->listCars();

        return response()->json($cars);
    }

    #[OA\Delete(
        path: '/admin/cars/{car}',
        operationId: 'adminCarDelete',
        summary: 'Delete a car',
        tags: ['Admin - Cars'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 404, description: 'Car not found'),
        ]
    )]
    /**
     * Delete a car.
     */
    public function destroy(Car $car): JsonResponse
    {
        $this->cars->deleteCar($car);

        return response()->json(['message' => 'Car deleted successfully.']);
    }
}
