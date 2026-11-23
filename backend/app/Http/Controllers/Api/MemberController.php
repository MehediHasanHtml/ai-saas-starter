<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection($this->tenant()->members()->orderBy('name')->get());
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate(['role' => ['required', Rule::in(Role::assignable())]]);
        $organization = $this->tenant();

        $current = $organization->roleOf($user);
        abort_unless($current, 404);
        abort_if($current === Role::Owner, 422, 'The owner role cannot be changed.');

        $organization->members()->updateExistingPivot($user->id, ['role' => $data['role']]);

        return new UserResource($organization->members()->find($user->id));
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $organization = $this->tenant();

        $role = $organization->roleOf($user);
        abort_unless($role, 404);
        abort_if($role === Role::Owner, 422, 'The owner cannot be removed.');

        $organization->members()->detach($user->id);

        if ((int) $user->current_organization_id === $organization->id) {
            $user->forceFill(['current_organization_id' => $user->organizations()->value('organizations.id')])->save();
        }

        return response()->json(['message' => 'Member removed.']);
    }
}
