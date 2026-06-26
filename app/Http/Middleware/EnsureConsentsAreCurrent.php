<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wymusza akceptację AKTUALNYCH wersji wymaganych dokumentów prawnych. Gdy
 * dokument się zmieni (nowa wersja), zalogowany użytkownik zostaje
 * przekierowany na stronę zgód, zanim wejdzie dalej. Strona zgód i wylogowanie
 * są wyłączone spod reguły, żeby nie zapętlić przekierowania.
 */
class EnsureConsentsAreCurrent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($request->routeIs('consents.*', 'logout')) {
            return $next($request);
        }

        if ($user->outstandingConsents()->isNotEmpty()) {
            return redirect()->route('consents.show');
        }

        return $next($request);
    }
}
