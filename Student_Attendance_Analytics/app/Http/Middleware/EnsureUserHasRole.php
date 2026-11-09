<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets a request through only when the logged-in user has one of the given roles.
 * Usage in routes: ->middleware('role:admin') or ->middleware('role:admin,teacher')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, __('You do not have permission to open this page.'));
        }

        return $next($request);
    }
}
