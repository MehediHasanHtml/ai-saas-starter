<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_super_admin' => $this->is_super_admin,
            'current_organization_id' => $this->current_organization_id,
            'role' => $this->whenPivotLoaded('organization_user', fn () => $this->pivot->role),
            'created_at' => $this->created_at,
        ];
    }
}
