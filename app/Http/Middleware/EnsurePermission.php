<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate by permission. ANY-of by default; ALL-of when invoked as `permission.all:`.
 *
 *   Route::get(...)->middleware('permission:role.view,role.manage');           // any of these
 *   Route::get(...)->middleware('permission.all:role.view,role.manage');       // all of these
 *
 * Roles are intentionally NOT a thing this middleware understands — gating by
 * role bypasses direct grants and per-org customisation (docs/authorization.md §11).
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $mode = 'any';
        $perms = [];
        foreach ($permissions as $p) {
            if ($p === 'all') {
                $mode = 'all';
                continue;
            }
            $perms[] = $p;
        }

        $check = $mode === 'all'
            ? fn ($p) => $user->can($p)
            : fn ($p) => $user->can($p);

        $passed = $mode === 'all'
            ? count(array_filter($perms, $check)) === count($perms)
            : count(array_filter($perms, $check)) > 0;

        if (! $passed) {
            abort(403, 'Required permission missing: ' . implode(', ', $perms));
        }

        return $next($request);
    }
}
