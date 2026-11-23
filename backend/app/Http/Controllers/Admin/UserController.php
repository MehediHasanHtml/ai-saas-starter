<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->withCount('organizations')
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
            })
            ->latest()
            ->paginate(25, ['id', 'name', 'email', 'is_super_admin', 'last_seen_at', 'created_at']);

        return response()->json($users);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['is_super_admin' => ['required', 'boolean']]);

        abort_if(
            $user->is($request->user()) && ! $data['is_super_admin'],
            422,
            'You cannot remove your own admin access.'
        );

        $user->forceFill(['is_super_admin' => $data['is_super_admin']])->save();

        return response()->json(['user' => $user->only('id', 'name', 'email', 'is_super_admin')]);
    }
}
