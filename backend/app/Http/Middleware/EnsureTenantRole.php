<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('tenant.role:owner,admin') — must run after `tenant`. */
class EnsureTenantRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->attributes->get('tenant_role');

        abort_unless($role && in_array($role, $roles, true), 403, 'Your role does not allow this action.');

        return $next($request);
    }
}
