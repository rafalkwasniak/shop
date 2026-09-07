<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\PackagePurchaseRequest;
use App\Services\AiQuota;
use App\Services\PackagePaymentService;
use App\Support\PackageFeatures;
use App\Support\PackageUpgrade;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * „Mój pakiet" — co sprzedawca ma wykupione, do kiedy i co dostanie wyżej.
 *
 * Dotąd nie było takiego miejsca: pakiet dawał się ustawić wyłącznie z konsoli
 * admina, a sprzedawca nie widział ani nazwy pakietu, ani terminu, ani tego, ile
 * z limitów zużył. Ekran jest też docelowym miejscem, do którego skieruje zakup
 * pakietu ze strony głównej.
 *
 * Zakup online DZIAŁA (patrz `purchase()` niżej): płatność idzie przez konto
 * platformy w Paynow, a po zaksięgowaniu pakiet włącza się sam i wychodzi
 * faktura. Wcześniejsza wersja tego opisu mówiła, że zakupu nie ma — zostało
 * to po etapie, w którym konto platformy dopiero czekało na weryfikację.
 */
class PackageController extends Controller
{
    public function show(Request $request, AiQuota $quota): Renderable
    {
        $shop = $request->user()->currentShop();

        abort_if($shop === null, 404);

        $productLimit = (int) $shop->entitlement('max_products');
        $aiLimit = (int) $shop->entitlement('ai_weekly_limit');

        return view('seller.package.show', [
            'shop' => $shop,
            'features' => PackageFeatures::forShop($shop),
            // Pakiety wyżej w cenniku — z zaznaczeniem, co w nich dochodzi.
            'upgrades' => collect(PackageFeatures::landing())
                ->filter(fn (array $package): bool => $package['price_yearly'] > $shop->priceYearly())
                ->values()
                ->all(),
            // Wycena przejścia na każdy droższy pakiet — sprzedawca ma widzieć
            // kwotę, nie musieć o nią pytać.
            'quotes' => PackageUpgrade::upgradeQuotes($shop),
            // Przedłużenie TEGO SAMEGO pakietu — osobno od wycen wyższych, bo
            // rządzi się inną regułą terminu (rok dokleja się do posiadanego).
            'renewal' => PackageUpgrade::renewal($shop),
            // Zejście niżej WYŁĄCZNIE w oknie odnowienia (≤ 30 dni do końca albo
            // po terminie) — poza nim lista jest pusta i ekran o tym nie mówi
            // przyciskiem, tylko zdaniem „od kiedy".
            'downsizes' => PackageUpgrade::downsizeQuotes($shop),
            'usage' => [
                'products' => $shop->products()->count(),
                'products_limit' => $productLimit,
                'ai_used' => $quota->used($shop),
                'ai_limit' => $aiLimit,
            ],
            'contactEmail' => config('company.email') ?: config('mail.from.address'),
            // Zakup online działa dopiero ze skonfigurowanym kontem platformy —
            // bez niego ekran pokazuje ścieżkę kontaktową, żadnych martwych przycisków.
            // Zakup wymaga DWÓCH rzeczy: konta płatniczego platformy i danych
            // do faktury. Bez drugiego wzięlibyśmy pieniądze, których nie da się
            // udokumentować — dlatego blokujemy przed płatnością, nie po.
            'onlinePurchase' => filled(config('services.paynow.platform.api_key')) && $shop->canBeInvoicedForPackage(),
            'invoiceRecipient' => $shop->packageInvoiceRecipient(),
            'invoiceDataMissing' => ! $shop->canBeInvoicedForPackage(),
            // NAJNOWSZA płatność (nie „najnowsza pending"): po odrzuceniu ekran ma
            // powiedzieć „nie udało się, spróbuj ponownie", a ponowienie tworzy
            // nowszy wiersz, który naturalnie przejmuje baner.
            'latestPayment' => $shop->packagePayments()->whereNotNull('payment_id')->latest('id')->first(),
            // Historia PAKIETU: zmiany z płatności i nadane ręcznie, plus
            // nieudane próby płatności (żeby sprzedawca wiedział, że coś nie
            // przeszło). Sieroty po nieudanym starcie bramki pomijamy.
            'changes' => $shop->packageChanges()->with('payment')->get(),
            'failedPayments' => $shop->packagePayments()
                ->where('status', 'failed')->whereNotNull('payment_id')->get(),
        ]);
    }

    /**
     * Start zakupu pakietu: wycena → migawka → przekierowanie do Paynow.
     * Kwotę i termin liczy PackageUpgrade — dokładnie te same liczby, które
     * sprzedawca widział na ekranie.
     */
    public function purchase(PackagePurchaseRequest $request, string $package, PackagePaymentService $payments): RedirectResponse
    {
        $shop = $request->user()->currentShop();

        abort_if($shop === null, 404);

        // Tylko pakiety Z OFERTY. `config('shop.packages')` zawiera także presety
        // spoza cennika („Sklep dedykowany" — cena 0, wszystko włączone), więc
        // samo sprawdzenie klucza pozwoliłoby sprzedawcy wysłać ich slug wprost
        // z formularza i wziąć komplet funkcji za darmo.
        abort_unless(array_key_exists($package, PackageFeatures::purchasable()), 404);

        // Twarda bramka: bez danych do faktury nie przyjmujemy płatności.
        if (! $shop->canBeInvoicedForPackage()) {
            return redirect()
                ->route('seller.package.show')
                ->with('error', 'Uzupełnij dane do faktury w „Mój sklep", zanim kupisz pakiet — bez nich nie wystawimy dokumentu.');
        }

        $redirectUrl = $payments->start(
            $shop,
            $package,
            route('seller.package.show', ['platnosc' => 'powrot']),
            $request->ip(),
        );

        if ($redirectUrl === null) {
            return redirect()
                ->route('seller.package.show')
                ->with('error', 'Nie udało się rozpocząć płatności. Spróbuj za chwilę albo napisz do nas.');
        }

        return redirect()->away($redirectUrl);
    }
}
