<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return OrganizationResource::collection($request->user()->organizations()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $organization = $request->user()->createOrganization($data['name']);
        $organization = $request->user()->organizations()->find($organization->id);

        return (new OrganizationResource($organization))->response()->setStatusCode(201);
    }

    public function current(Request $request): OrganizationResource
    {
        return new OrganizationResource(
            $request->user()->organizations()->find($this->tenant()->id)
        );
    }

    public function switch(Request $request, Organization $organization): OrganizationResource
    {
        $membership = $request->user()->organizations()->find($organization->id);
        abort_unless($membership, 403, 'You are not a member of this organization.');

        $request->user()->forceFill(['current_organization_id' => $organization->id])->save();

        return new OrganizationResource($membership);
    }

    public function update(Request $request): OrganizationResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'settings.default_provider' => ['sometimes', 'nullable', Rule::in(array_keys(config('ai.providers')))],
            'settings.default_model' => ['sometimes', 'nullable', 'string', 'max:100'],
            'settings.system_prompt' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ]);

        $organization = $this->tenant();

        if (isset($data['name'])) {
            $organization->name = $data['name'];
        }

        if (isset($data['settings'])) {
            $organization->settings = array_merge($organization->settings ?? [], $data['settings']);
        }

        $organization->save();

        return new OrganizationResource($request->user()->organizations()->find($organization->id));
    }
}
