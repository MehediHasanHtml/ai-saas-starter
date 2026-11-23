<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Organization */
class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $plan = $this->plan();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'personal' => $this->personal,
            'role' => $this->whenPivotLoaded('organization_user', fn () => $this->pivot->role),
            'plan' => ['key' => $plan->key, 'name' => $plan->name],
            'settings' => [
                'default_provider' => $this->setting('default_provider', config('ai.default')),
                'default_model' => $this->setting('default_model'),
                'system_prompt' => $this->setting('system_prompt'),
            ],
            'suspended' => $this->isSuspended(),
            'created_at' => $this->created_at,
        ];
    }
}
