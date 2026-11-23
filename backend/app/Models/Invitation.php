<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Not tenant-scoped via global scope because invitees accept them before
 * they belong to the organization. Controllers constrain by organization.
 */
class Invitation extends Model
{
    protected $fillable = ['organization_id', 'invited_by', 'email', 'role', 'token', 'expires_at'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
