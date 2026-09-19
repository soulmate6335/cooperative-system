<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        if (in_array($user->status, ['inactive', 'suspended', 'pending'], true)) {
            return response()->json(['success' => false, 'message' => 'This account is not permitted to log in.'], 403);
        }

        $user->update(['last_login_at' => now()]);
        $user->load(['roles.permissions', 'member']);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => UserResource::make($user),
                'token' => $user->createToken('api')->plainTextToken,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logout successful.']);
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user()->load(['roles.permissions', 'member']));
    }
}
