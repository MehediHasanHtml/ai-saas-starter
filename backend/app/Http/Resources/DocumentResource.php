<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Document */
class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'status' => $this->status->value,
            'error' => $this->error,
            'chunk_count' => $this->chunk_count,
            'token_count' => $this->token_count,
            'uploaded_by' => $this->whenLoaded('user', fn () => $this->user?->only('id', 'name')),
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
        ];
    }
}
