<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Admin - Users', description: 'User management operations for administrators.')]
/**
 * Handles user management operations for administrators.
 */
class AdminUserController extends Controller
{
    /**
     * Create a new admin user controller instance.
     */
    public function __construct(
        private readonly UserServiceInterface $users,
    ) {}

    #[OA\Get(
        path: '/admin/users',
        operationId: 'adminUserList',
        summary: 'List all users',
        tags: ['Admin - Users'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    /**
     * List all users.
     */
    public function index(): JsonResponse
    {
        $users = $this->users->listUsers();

        return response()->json($users);
    }

    #[OA\Delete(
        path: '/admin/users/{user}',
        operationId: 'adminUserDelete',
        summary: 'Delete a user',
        tags: ['Admin - Users'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
            new OA\Response(response: 403, description: 'Cannot delete own admin account'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    /**
     * Delete a user.
     */
    public function destroy(User $user): JsonResponse
    {
        $authUserId = (int) auth()->id();

        if ($authUserId === $user->id) {
            return response()->json(['message' => 'Cannot delete your own admin account.'], 403);
        }

        $this->users->deleteUser($user, $authUserId);

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
