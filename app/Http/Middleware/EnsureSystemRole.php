<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route to users holding at least one of the listed SYSTEM roles
 * (organization_id = NULL on the role). Used on /api/v1/admin/* routes.
 *
 *   Route::get(...)->middleware('system-role:super-admin,platform-support');
 */
final class EnsureSystemRole
{
    public function handle(Request $request, Closure $next, string ...$roleSlugs): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        foreach ($roleSlugs as $slug) {
            if ($user->hasSystemRole($slug)) {
                return $next($request);
            }
        }

        abort(403, 'Required system role missing: ' . implode(', ', $roleSlugs));
    }
}
