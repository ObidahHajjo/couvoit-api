<?php

namespace App\Http\Controllers;

use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Services\Interfaces\BrandServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class BrandController
 *
 * HTTP controller for Brand endpoints.
 */
class BrandController extends Controller
{
    /**
     * @param BrandServiceInterface $brands
     */
    public function __construct(
        private readonly BrandServiceInterface $brands
    ) {}

    /**
     * GET /brands
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $brands = $this->brands->getBrands();

        return BrandResource::collection($brands)
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * GET /brands/{brand}
     *
     * @param  Brand  $brand
     * @return JsonResponse
     */
    public function show(Brand $brand): JsonResponse
    {
        // If you want to enforce loading from DB through service/repo:
        // $brand = $this->brands->getBrandById($brand->id);

        return (new BrandResource($brand))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
