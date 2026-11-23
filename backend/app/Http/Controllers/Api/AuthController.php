<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(8)],
            'organization_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
            ]);

            // Every user gets a workspace on sign-up; teams are created later
            // or joined through invitations.
            $user->createOrganization(
                $data['organization_name'] ?? "{$user->name}'s Workspace",
                personal: empty($data['organization_name']),
            );

            return $user;
        });

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user' => new UserResource($user->fresh()),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'web', ['*'], now()->addDays(30))->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $organizations = $user->organizations()->orderBy('name')->get();

        // Self-heal: point current_organization_id at a valid membership.
        if (! $organizations->contains('id', $user->current_organization_id) && $organizations->isNotEmpty()) {
            $user->forceFill(['current_organization_id' => $organizations->first()->id])->saveQuietly();
        }

        return response()->json([
            'user' => new UserResource($user),
            'organizations' => OrganizationResource::collection($organizations),
            'current_organization_id' => $user->current_organization_id,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
