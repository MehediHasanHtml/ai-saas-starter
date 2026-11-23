<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Entitlements;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organizations = Organization::query()
            ->with(['owner:id,name,email', 'subscriptions'])
            ->withCount(['members', 'documents', 'conversations'])
            ->when($request->query('q'), function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('slug', 'ilike', $like));
            })
            ->latest()
            ->paginate(25);

        $organizations->getCollection()->transform(fn (Organization $org) => $this->present($org));

        return response()->json($organizations);
    }

    public function show(Organization $organization, Entitlements $entitlements): JsonResponse
    {
        $organization->load(['owner:id,name,email', 'members:id,name,email', 'subscriptions'])
            ->loadCount(['members', 'documents', 'conversations']);

        return response()->json([
            'organization' => $this->present($organization),
            'members' => $organization->members->map(fn ($m) => [
                'id' => $m->id, 'name' => $m->name, 'email' => $m->email, 'role' => $m->pivot->role,
            ]),
            'usage' => $entitlements->usage($organization),
        ]);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $data = $request->validate([
            'plan_override' => ['sometimes', 'nullable', Rule::in(array_keys(config('plans.plans')))],
            'suspended' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('plan_override', $data)) {
            $organization->plan_override = $data['plan_override'];
        }

        if (array_key_exists('suspended', $data)) {
            $organization->suspended_at = $data['suspended'] ? now() : null;
        }

        $organization->save();

        return response()->json(['organization' => $this->present($organization->loadCount(['members', 'documents', 'conversations']))]);
    }

    private function present(Organization $org): array
    {
        $plan = $org->plan();

        return [
            'id' => $org->id,
            'name' => $org->name,
            'slug' => $org->slug,
            'owner' => $org->relationLoaded('owner') ? $org->owner?->only('id', 'name', 'email') : null,
            'plan' => $plan->key,
            'plan_override' => $org->plan_override,
            'subscription_status' => $org->subscriptions->first()?->stripe_status,
            'suspended' => $org->isSuspended(),
            'members_count' => $org->members_count ?? null,
            'documents_count' => $org->documents_count ?? null,
            'conversations_count' => $org->conversations_count ?? null,
            'created_at' => $org->created_at,
        ];
    }
}
