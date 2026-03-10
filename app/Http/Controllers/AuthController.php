<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Resources\AuthTokenResource;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

/**
 * Auth endpoints (Local JWT).
 */
#[OA\Tag(name: 'Auth', description: 'Authentication endpoints (register/login/refresh).')]
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService
    ) {}

    #[OA\Post(
        path: '/register',
        operationId: 'authRegister',
        summary: 'Register',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AuthRequestPayload')
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 201, description: 'Created', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function register(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->register($data['email'], $data['password']);

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    #[OA\Post(
        path: '/login',
        operationId: 'authLogin',
        summary: 'Login',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/AuthRequestPayload')
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function login(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->login($data['email'], $data['password']);

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/refresh',
        operationId: 'authRefresh',
        summary: 'Refresh token',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/RefreshRequestPayload')
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->refresh($data['refresh_token']);

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }
}
