<?php

use App\Http\Middleware\AuthenticateCustomer;
use App\Http\Middleware\EnsureConsentsAreCurrent;
use App\Http\Middleware\EnsureRegistrationIsOpen;
use App\Http\Middleware\EnsureSaasMode;
use App\Http\Middleware\EnsureSectionAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RecordStorefrontTraffic;
use App\Http\Middleware\ResolveShop;
use App\Http\Middleware\SecurityHeaders;
use App\Services\DiscordErrorReporter;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'ensure.consents' => EnsureConsentsAreCurrent::class,
            'section' => EnsureSectionAccess::class,
            'tenant' => ResolveShop::class,
            'record.traffic' => RecordStorefrontTraffic::class,
            'auth.customer' => AuthenticateCustomer::class,
            'registration.open' => EnsureRegistrationIsOpen::class,
            // Trasy warstwy platformy — nieobecne w sklepie dedykowanym.
            'saas' => EnsureSaasMode::class,
        ]);

        // `saas` musi wykonać się PRZED uwierzytelnianiem, a kolejność wypisana
        // przy trasie tego nie gwarantuje: Laravel sortuje middleware według
        // własnej listy priorytetów, na której `Authenticate` jest, a nasze nie.
        // Bez tego wpisu `/administrator/panel` w sklepie dedykowanym najpierw
        // odsyła do logowania (302), a zalogowanemu sprzedawcy pokazuje 403 —
        // czyli w obu przypadkach potwierdza, że taki adres istnieje. Ma
        // odpowiadać 404, niezależnie od tego, czy i jako kto ktoś jest zalogowany.
        $middleware->prependToPriorityList(
            before: Authenticate::class,
            prepend: EnsureSaasMode::class,
        );

        // Nagłówki bezpieczeństwa na KAŻDEJ odpowiedzi webowej — centrala i
        // wszystkie storefronty naraz (config/security.php).
        $middleware->web(append: [SecurityHeaders::class]);

        // Zgoda na ciasteczka BEZ szyfrowania. Nie jest sekretem — niesie samo
        // „granted" albo „declined" — a musi pozostać czytelna poza cyklem
        // żądania: dla skryptu w przeglądarce (gdyby doszedł tryb zgody Google)
        // i przy diagnozowaniu, dlaczego komuś pokazuje się baner. Zaszyfrowana
        // wartość jest dla nich nieczytelna i cicho traktowana jak brak decyzji.
        // Nazwa WPISANA WPROST, nie przez config(): ten plik wykonuje się, zanim
        // konfiguracja zostanie wczytana, więc wywołanie config() wywraca całą
        // aplikację (biały ekran, nie ostrzeżenie). Wartość musi pozostać zgodna
        // z `config/cookies.php` — pilnuje tego test.
        $middleware->encryptCookies(except: ['cookie_consent']);

        // Niezalogowani trafiają na ekran logowania.
        $middleware->redirectGuestsTo(fn () => route('login'));

        // Webhook Paynow przychodzi z zewnątrz, bez tokenu CSRF — broni go podpis
        // (weryfikowany w kontrolerze), nie sesja. To jedyny wyjątek.
        $middleware->validateCsrfTokens(except: [
            'platnosci/paynow/webhook',
            'platnosci/paynow/pakiety/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Błędy w JSON dla żądań AJAX/JSON (np. „Popraw przez AI"); zwykłe
        // formularze webowe nadal dostają redirect z błędami w sesji.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Report unhandled exceptions to Discord, in addition to the log.
        $exceptions->report(function (Throwable $e): void {
            app(DiscordErrorReporter::class)->report($e);
        });
    })->create();
