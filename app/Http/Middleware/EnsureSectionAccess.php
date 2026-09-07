<?php

namespace App\Http\Middleware;

use App\Enums\PanelSection;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wpuszcza do działu panelu tylko tego, kto ma do niego dostęp.
 *
 * Użycie: ->middleware('section:orders').
 *
 * Właściciel przechodzi zawsze — dział jest pojęciem wyłącznie pracowniczym.
 * Pracownik przechodzi, gdy jego czynne członkostwo wymienia ten dział.
 *
 * 403, nie 404: pracownik WIE, że sklep ma zamówienia, bo pracuje w tym samym
 * sklepie. Udawanie, że adresu nie ma, nie chroni przed niczym, a wygląda na
 * usterkę. To co innego niż cudzy sklep, gdzie 404 jest na miejscu.
 */
class EnsureSectionAccess
{
    public function handle(Request $request, Closure $next, string $section): Response
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless($user->canAccess(PanelSection::from($section)), 403);

        return $next($request);
    }
}
