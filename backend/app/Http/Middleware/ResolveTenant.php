<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current organization from the X-Organization-Id header (or the
 * user's last used organization), verifies membership and binds it to the
 * TenantContext so every tenant-scoped query is constrained automatically.
 */
class ResolveTenant
{
    public const HEADER = 'X-Organization-Id';

    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user, 401);

        $organizationId = $request->header(self::HEADER) ?: $user->current_organization_id;

        if (! $organizationId || ! ctype_digit((string) $organizationId)) {
            abort(409, 'No organization selected. Create or switch to an organization first.');
        }

        /** @var Organization|null $organization */
        $organization = $user->organizations()->whereKey((int) $organizationId)->first();

        if (! $organization) {
            abort(403, 'You are not a member of this organization.');
        }

        if ($organization->isSuspended()) {
            abort(403, 'This organization has been suspended. Contact support.');
        }

        $this->tenant->set($organization);
        $request->attributes->set('tenant_role', $organization->pivot->role);

        if ((int) $user->current_organization_id !== $organization->id) {
            $user->forceFill(['current_organization_id' => $organization->id])->saveQuietly();
        }

        return $next($request);
    }
}
