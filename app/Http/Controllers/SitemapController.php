<?php

namespace App\Http\Controllers;

use App\Enums\LegalDocumentType;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Mapy stron (`sitemap.xml`) — jedna klasa na OBA konteksty, bo pytanie jest
 * dokładnie to samo: „co ten host wystawia wyszukiwarce".
 *
 * Każdy host ma WŁASNĄ mapę pod własnym adresem: centrala wymienia landing
 * i dokumenty prawne, storefront — wyłącznie swój sklep. Nie ma jednej zbiorczej
 * mapy: adresy powinny leżeć na tym samym hoście co mapa (Google traktuje mapę
 * cross-host nieufnie), a lista wszystkich map byłaby publicznym spisem sklepów.
 *
 * Do mapy wchodzi TYLKO to, co ma trafić do wyników. Koszyk, kasa, płatność,
 * konto klienta i zwroty mają `noindex` po audycie SEO — wpisanie ich tutaj byłoby
 * sprzecznością z samym sobą.
 *
 * `lastmod` bierzemy z `updated_at`. Świadomy kompromis: masowa operacja na bazie
 * (przeliczenie cen, migracja) ustawi wszystkim tę samą datę i Google potraktuje
 * to jak szum — takie zabiegi są jednak rzadkie i da się je wykonać bez dotykania
 * `updated_at`, a bieżąca informacja o zmianie jest warta więcej.
 *
 * Bez indeksu map i paginacji: największy pakiet to 96 produktów (~100 adresów)
 * przy limicie Google 50 000 adresów na plik.
 */
class SitemapController extends Controller
{
    /**
     * Mapa centrali: strony platformy, nie sklepów. Świadomie osobna metoda —
     * przy grafice OG, Analytics i zgodzie na ciasteczka funkcja powstawała
     * najpierw dla sklepów, a strony platformy wypadały z zakresu.
     */
    public function central(): Response
    {
        $urls = [
            ['loc' => url('/'), 'priority' => '1.0'],
            ['loc' => route(LegalDocumentType::Terms->routeName()), 'priority' => '0.3'],
            ['loc' => route(LegalDocumentType::Privacy->routeName()), 'priority' => '0.3'],
        ];

        return $this->xml($urls);
    }

    /**
     * Mapa jednego sklepu. Sklep w szkicu (bez aktywnych produktów) pokazuje
     * „już wkrótce", więc mapy nie dostaje wcale — zapraszanie Google do
     * indeksowania pustej strony psuje sklepowi ocenę, zanim wystartuje.
     */
    public function storefront(Request $request): Response
    {
        $shop = $request->attributes->get('shop');

        abort_unless($shop->isVisible(), 404);

        $base = 'https://'.$shop->host();

        $urls = [
            ['loc' => $base.'/', 'lastmod' => $shop->updated_at, 'priority' => '1.0'],
            ['loc' => $base.'/produkty', 'priority' => '0.9'],
        ];

        // Produkt bez stanu magazynowego ZOSTAJE w mapie: strona istnieje i jest
        // sensowna, a towar wraca. Wyrzucanie go i przywracanie to sztuczne
        // migotanie mapy, które Google odczytuje gorzej niż stabilny adres.
        foreach ($shop->products()->where('is_active', true)->get() as $product) {
            $urls[] = [
                'loc' => $base.$product->storefrontPath(),
                'lastmod' => $product->updated_at,
                'priority' => '0.8',
            ];
        }

        $urls[] = ['loc' => $base.'/informacje', 'priority' => '0.4'];

        // `informationMenu()` to jedno źródło pozycji „Informacje" dla nagłówka,
        // stopki i mapy — strona ukryta w menu nie ma prawa wypłynąć tutaj.
        foreach ($shop->informationMenu() as $item) {
            $urls[] = ['loc' => $base.$item['url'], 'priority' => '0.3'];
        }

        if ($shop->contactFormEnabled()) {
            $urls[] = ['loc' => $base.'/kontakt', 'priority' => '0.3'];
        }

        return $this->xml($urls);
    }

    /**
     * Składa dokument `urlset`. Odpowiedź jest budowana tutaj, a nie w widoku
     * Blade: XML nie znosi przypadkowej białej znaku przed deklaracją, a Blade
     * łatwo taki dokłada (jedna pusta linia w szablonie psuje cały plik).
     *
     * @param  list<array{loc: string, lastmod?: \Illuminate\Support\Carbon|null, priority?: string}>  $urls
     */
    private function xml(array $urls): Response
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.e($url['loc']).'</loc>';

            if (! empty($url['lastmod'])) {
                $lines[] = '    <lastmod>'.$url['lastmod']->toAtomString().'</lastmod>';
            }

            if (! empty($url['priority'])) {
                $lines[] = '    <priority>'.$url['priority'].'</priority>';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return response(implode("\n", $lines), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Adres mapy danego sklepu — do panelu sprzedawcy i do `robots.txt`.
     */
    public static function urlFor(Shop $shop): string
    {
        return 'https://'.$shop->host().'/sitemap.xml';
    }
}
