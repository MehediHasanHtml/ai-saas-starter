<?php

namespace App\Http\Controllers\Api;

use App\Billing\Entitlements;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Invitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Invitations return a shareable accept link. Hook a Mailable into store()
 * to email it (see README → "Sending invitation emails").
 */
class InvitationController extends Controller
{
    public function index(): JsonResponse
    {
        $invitations = $this->tenant()->invitations()
            ->where('expires_at', '>', now())
            ->latest()
            ->get(['id', 'email', 'role', 'expires_at', 'created_at']);

        return response()->json(['data' => $invitations]);
    }

    public function store(Request $request, Entitlements $entitlements): JsonResponse
    {
        $organization = $this->tenant();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['sometimes', Rule::in(Role::assignable())],
        ]);
        $email = strtolower($data['email']);

        abort_if(
            $organization->members()->where('email', $email)->exists(),
            422,
            'That person is already a member.'
        );

        $entitlements->ensureCanAddMember($organization);

        $token = Str::random(48);
        $invitation = Invitation::updateOrCreate(
            ['organization_id' => $organization->id, 'email' => $email],
            [
                'invited_by' => $request->user()->id,
                'role' => $data['role'] ?? Role::Member->value,
                'token' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ],
        );

        return response()->json([
            'data' => $invitation->only('id', 'email', 'role', 'expires_at'),
            'accept_url' => rtrim(config('saas.frontend_url'), '/')."/invite/{$token}",
        ], 201);
    }

    public function destroy(Invitation $invitation): JsonResponse
    {
        abort_unless((int) $invitation->organization_id === $this->tenant()->id, 404);
        $invitation->delete();

        return response()->json(['message' => 'Invitation revoked.']);
    }

    public function accept(Request $request, string $token): OrganizationResource
    {
        $user = $request->user();
        $invitation = Invitation::with('organization')->where('token', hash('sha256', $token))->first();

        abort_unless($invitation && ! $invitation->isExpired(), 404, 'This invitation is invalid or has expired.');
        abort_unless(
            strcasecmp($invitation->email, $user->email) === 0,
            403,
            'This invitation was sent to a different email address.'
        );

        $organization = $invitation->organization;

        DB::transaction(function () use ($organization, $invitation, $user) {
            $organization->members()->syncWithoutDetaching([$user->id => ['role' => $invitation->role]]);
            $user->forceFill(['current_organization_id' => $organization->id])->save();
            $invitation->delete();
        });

        return new OrganizationResource($user->organizations()->find($organization->id));
    }
}
