<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\AuthTokenResource;
use App\Models\User;
use App\Services\Interfaces\AuthServiceInterface;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Response;

#[OA\Tag(name: 'Auth', description: 'Authentication endpoints (register/login/refresh).')]
/**
 * Handles authentication endpoints.
 */
class AuthController extends Controller
{
    /**
     * Create a new auth controller instance.
     */
    public function __construct(
        private readonly AuthServiceInterface $authService,
        private readonly AuthFactory $auth,
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
    /**
     * Register a new user account.
     */
    public function register(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->register($data['email'], $data['password']);

        return (new AuthTokenResource($result))
            ->response()
            ->cookie($this->authCookie('access_token', $result['access_token'], 60))
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
    /**
     * Authenticate a user and issue tokens.
     */
    public function login(AuthRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->login($data['email'], $data['password']);

        return (new AuthTokenResource($result))
            ->response()
            ->cookie($this->authCookie('access_token', $result['access_token'], 21600))
            ->cookie($this->authCookie('refresh_token', $result['refresh_token'], 43200))
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
    /**
     * Rotate access and refresh tokens.
     */
    public function refresh(RefreshRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->refresh($data['refresh_token']);

        return (new AuthTokenResource($result))
            ->response()
            ->cookie($this->authCookie('access_token', $result['access_token'], 21600))
            ->cookie($this->authCookie('refresh_token', $result['refresh_token'], 43200))
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
    /**
     * Log out the authenticated user.
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return response()->json([
            'data' => [
                'message' => __('api.auth.logout_success'),
            ],
        ])->withoutCookie('access_token', $this->cookiePath(), $this->cookieDomain())
            ->withoutCookie('refresh_token', $this->cookiePath(), $this->cookieDomain());
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
    /**
     * Return the authenticated user profile.
     */
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->auth->guard()->user();

        if (! $user instanceof User) {
            abort(Response::HTTP_UNAUTHORIZED, __('api.errors.unauthorized'));
        }

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
    /**
     * Send a password reset link.
     */
    public function forgetPassword(ForgetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $status = $this->authService->forgetPassword($validated['email']);
        Log::info('status: '.$status);

        return response()->json([
            'message' => __('api.auth.forgot_password_notice'),
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
    /**
     * Reset a user password with a valid token.
     */
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
            'message' => __('api.auth.password_reset_success'),
            'status' => $status,
        ]);
    }

    /**
     * Build an authentication cookie.
     */
    private function authCookie(string $name, string $value, int $minutes): \Symfony\Component\HttpFoundation\Cookie
    {
        return Cookie::make(
            $name,
            $value,
            $minutes,
            $this->cookiePath(),
            $this->cookieDomain(),
            $this->cookieSecure(),
            true,
            false,
            $this->cookieSameSite(),
        );
    }

    /**
     * Get the configured auth cookie path.
     */
    private function cookiePath(): string
    {
        return (string) config('auth.cookies.path', '/');
    }

    /**
     * Get the configured auth cookie domain.
     */
    private function cookieDomain(): ?string
    {
        $domain = config('auth.cookies.domain');

        return is_string($domain) && strtolower($domain) !== 'null' && $domain !== ''
            ? $domain
            : null;
    }

    /**
     * Determine whether auth cookies must be secure.
     */
    private function cookieSecure(): bool
    {
        return (bool) config('auth.cookies.secure', false);
    }

    /**
     * Get the configured SameSite policy.
     */
    private function cookieSameSite(): string
    {
        return (string) config('auth.cookies.same_site', 'lax');
    }
}
