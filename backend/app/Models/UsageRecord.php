<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    public const TYPE_CHAT = 'chat';

    public const TYPE_EMBEDDING = 'embedding';

    public const TYPE_SEARCH = 'search';

    protected $fillable = [
        'organization_id', 'user_id', 'type', 'provider', 'model',
        'input_tokens', 'output_tokens', 'quantity', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
