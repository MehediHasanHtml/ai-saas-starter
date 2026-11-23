<?php

namespace App\Tenancy;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Add to any model that has an organization_id column.
 *
 *  - Queries are automatically constrained to the current tenant.
 *  - New rows are automatically stamped with the current tenant.
 *  - Writing a row for a different tenant than the current one throws.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $tenantId = app(TenantContext::class)->id();

            if (empty($model->organization_id)) {
                if ($tenantId === null) {
                    throw new LogicException('Cannot create '.static::class.' without a tenant.');
                }
                $model->organization_id = $tenantId;
            } elseif ($tenantId !== null && (int) $model->organization_id !== $tenantId) {
                throw new LogicException('Refusing to write '.static::class.' for a different tenant.');
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Query across all tenants (platform admin, console commands, jobs). */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
