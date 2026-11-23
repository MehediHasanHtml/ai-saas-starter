<?php

namespace App\Models;

use App\Billing\Plan;
use App\Enums\Role;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;

/**
 * The tenant. Also the Stripe customer: the whole team shares one plan.
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use Billable, HasFactory;

    protected $fillable = ['name', 'slug', 'owner_id', 'personal', 'plan_override', 'suspended_at', 'settings'];

    protected function casts(): array
    {
        return [
            'personal' => 'boolean',
            'settings' => 'array',
            'suspended_at' => 'datetime',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            if (empty($org->slug)) {
                $base = Str::slug($org->name) ?: 'org';
                $slug = $base;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.Str::lower(Str::random(5));
                }
                $org->slug = $slug;
            }
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    // Tenant-owned relations bypass the global tenant scope on purpose: the
    // relation itself already constrains by organization_id, and this lets
    // admin screens and jobs use them without a resolved tenant.
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class)->withoutGlobalScopes();
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class)->withoutGlobalScopes();
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class)->withoutGlobalScopes();
    }

    public function roleOf(User $user): ?Role
    {
        $role = $this->members()->whereKey($user->getKey())->first()?->pivot?->role;

        return $role ? Role::from($role) : null;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Resolve the effective plan: admin override > active Stripe subscription > free.
     */
    public function plan(): Plan
    {
        if ($this->plan_override && Plan::exists($this->plan_override)) {
            return Plan::find($this->plan_override);
        }

        $subscription = $this->subscription('default');

        if ($subscription && $subscription->valid()) {
            return Plan::forStripePrice($subscription->stripe_price) ?? Plan::default();
        }

        return Plan::default();
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }
}
