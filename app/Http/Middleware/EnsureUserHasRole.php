<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Przepuszcza tylko zalogowanych z jedną z podanych ról; inaczej 403.
     *
     * Użycie: ->middleware('role:admin') lub 'role:admin,seller'.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
