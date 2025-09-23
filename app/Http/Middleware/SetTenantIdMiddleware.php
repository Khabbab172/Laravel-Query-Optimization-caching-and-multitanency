<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetTenantIdMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if a user is authenticated
        if (Auth::check()) {
            // Retrieve the authenticated user's tenant_id and store it.
            // This is an optional step, as the Eloquent global scope
            // can already access it via Auth::user(), but it can be useful
            // for other parts of your application.
            $tenantId = Auth::user()->tenant_id;

            // Example: Store the tenant ID in the session
            session(['tenant_id' => $tenantId]);

            // Example: Set a global configuration value
            config(['app.tenant_id' => $tenantId]);
        }

        return $next($request);
    }
}
