<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Interfaces\UserServiceInterface;
use Illuminate\Http\JsonResponse;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $users,
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->users->listUsers();

        return response()->json($users);
    }

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
