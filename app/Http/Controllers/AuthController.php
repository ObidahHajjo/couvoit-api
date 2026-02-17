<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\AuthTokenResource;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService
    ) {}

    /**
     * POST /register
     */
    public function register(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->register($data['email'], $data['password']);

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * POST /login
     */
    public function login(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->login($data['email'], $data['password']);

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * POST /refresh
     */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->refresh($data['refresh_token']);

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
