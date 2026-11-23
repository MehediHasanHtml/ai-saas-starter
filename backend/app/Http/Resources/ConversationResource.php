<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Conversation */
class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'provider' => $this->provider,
            'model' => $this->model,
            'system_prompt' => $this->system_prompt,
            'use_knowledge_base' => $this->use_knowledge_base,
            'document_ids' => $this->document_ids,
            'last_message_at' => $this->last_message_at,
            'created_at' => $this->created_at,
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
