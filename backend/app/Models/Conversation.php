<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'user_id', 'title', 'provider', 'model', 'system_prompt',
        'use_knowledge_base', 'document_ids', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'use_knowledge_base' => 'boolean',
            'document_ids' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->withoutGlobalScopes()->orderBy('id');
    }
}
