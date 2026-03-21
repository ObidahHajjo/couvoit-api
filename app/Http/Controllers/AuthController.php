<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\AuthTokenResource;
use App\Models\User;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

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
            ->cookie(
                'access_token',
                $result['access_token'],
                60,
                '/',              // path
                null,             // domain (null = current domain)
                true,             // Secure (HTTPS only)
                true,             // HttpOnly (not accessible via JS)
                false,            // raw
                'Strict'          // SameSite
            )
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
            ->cookie(
                'access_token',
                $result['access_token'],
                60,
                '/',              // path
                null,             // domain (null = current domain)
                false,             // Secure (HTTPS only)
                true,             // HttpOnly (not accessible via JS)
                false,            // raw
                'Lax'          // SameSite
            )->cookie(
                'refresh_token',
                $result['refresh_token'],
                43200,
                '/',
                null,
                false,
                true,
                false,
                'Lax'
            )
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
            ->cookie(
                'access_token',
                $result['access_token'],
                21600,
                '/',              // path
                null,             // domain (null = current domain)
                true,             // Secure (HTTPS only)
                true,             // HttpOnly (not accessible via JS)
                false,            // raw
                'Strict'          // SameSite
            )
            ->cookie(
                'refresh_token',
                $result['refresh_token'],
                43200,
                '/',
                null,
                true,
                true,
                false,
                'Strict'
            )
            ->setStatusCode(Response::HTTP_OK);
    }

    #[OA\Post(
        path: '/logout',
        operationId: 'authLogout',
        summary: 'Logout',
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
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'data' => [
                'message' => 'Logged out successfully',
            ],
        ])->withoutCookie('access_token', '/')
            ->withoutCookie('refresh_token', '/');
    }

    #[OA\Post(
        path: '/me',
        operationId: 'authMe',
        summary: 'me',
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/AuthTokenResponse')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();

        $user->refresh();
        $user->loadMissing(['person', 'role']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'role' => [
                    'id' => $user->role_id,
                    'name' => $user->roleName(),
                ],
                'person' => [
                    'id' => $user->person?->id,
                    'first_name' => $user->person?->first_name,
                    'last_name' => $user->person?->last_name,
                    'pseudo' => $user->person?->pseudo,
                    'phone' => $user->person?->phone,
                    'car_id' => $user->person?->car_id,
                ],
                'permissions' => [
                    'can_view_bookings' => $user->canBookTrip() || $user->isAdmin(),
                    'can_book_trip' => $user->canBookTrip(),
                    'can_cancel_booking' => $user->canBookTrip() || $user->isAdmin(),
                    'can_edit_profile' => true,
                    'can_publish_trip' => $user->canPublishTrip(),
                    'can_manage_own_trips' => $user->isDriver() || $user->isAdmin(),
                    'can_manage_all_trips' => $user->canManageAllTrips(),
                    'can_manage_all_users' => $user->canManageAllUsers(),
                    'can_manage_all_bookings' => $user->canManageAllBookings(),
                ],
            ],
        ]);
    }

    #[OA\Post(
        path: '/forgot-password',
        operationId: 'authForgotPassword',
        summary: 'Forgot-password',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordPayload')
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ForgotPasswordPayload')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function forgetPassword(ForgetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = $this->authService->forgetPassword($validated['email']);
        Log::info('status: '.$status);

        return response()->json([
            'message' => 'If an account exists for this email, a reset link has been sent.',
            'status' => $status,
        ]);
    }

    #[OA\Post(
        path: '/reset-password',
        operationId: 'authResetPassword',
        summary: 'Reset-password',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordPayload')
        ),
        tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'OK', content: new OA\JsonContent(ref: '#/components/schemas/ResetPasswordPayload')),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = $this->authService->resetPassword($validated);

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
                'status' => $status,
            ], 422);
        }

        return response()->json([
            'message' => 'Password reset successfully.',
            'status' => $status,
        ]);
    }
}
