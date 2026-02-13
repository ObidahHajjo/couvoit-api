<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\AuthRequest;
use App\Http\Requests\Auth\RefreshRequest;
use App\Services\Interfaces\AuthServiceInterface;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthServiceInterface $authService
    ) {}

    public function register(AuthRequest $request)
    {
        $data = $request->validated();
        try {
            $result = $this->authService->register($data['email'], $data['password']);
            return response()->json($result, 201);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'NOK', 'message' => $e->getMessage()], 400);
        }
    }

    public function login(AuthRequest $request)
    {
        $data = $request->validated();
        try {
            $result = $this->authService->login($data['email'], $data['password']);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'NOK', 'message' => 'Invalid credentials'], 401);
        }
    }

    public function refresh(RefreshRequest $request)
    {
        $data = $request->validated();
        try {
            $result = $this->authService->refresh($data['refresh_token']);
            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'NOK', 'message' => $e->getMessage()], 401);
        }
    }
}

