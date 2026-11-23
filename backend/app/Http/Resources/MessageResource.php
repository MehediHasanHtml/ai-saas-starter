<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Message */
class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'citations' => $this->citations ?? [],
            'provider' => $this->provider,
            'model' => $this->model,
            'input_tokens' => $this->input_tokens,
            'output_tokens' => $this->output_tokens,
            'latency_ms' => $this->latency_ms,
            'created_at' => $this->created_at,
        ];
    }
}
