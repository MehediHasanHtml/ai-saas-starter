<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id', 'conversation_id', 'role', 'content', 'citations',
        'provider', 'model', 'input_tokens', 'output_tokens', 'latency_ms',
    ];

    protected function casts(): array
    {
        return ['citations' => 'array'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
