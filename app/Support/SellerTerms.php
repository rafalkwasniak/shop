<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Shop;

/**
 * Wzór regulaminu sklepu dla sprzedawcy — dane kreatora i renderowanie.
 *
 * DLACZEGO ISTNIEJE: §10 ust. 1 Regulaminu Kramio wymaga, żeby sprzedawca
 * opublikował własny regulamin sprzedaży, a my dawaliśmy mu tylko zaślepkę
 * „regulamin w przygotowaniu" — publikowaną klientom od pierwszego dnia.
 *
 * TO JEST SZABLON, NIE GENERATOR. Zero AI w czasie działania: te same dane
 * zawsze dają ten sam tekst, więc prawnik przegląda wynik raz i ma pewność,
 * że u nikogo nie wyjdzie coś innego.
 *
 * GRANICA POMOCY (Rafał, 2026-08-16): „kreator tworzy dokument, nie audytuje
 * rzeczywistości". Podpowiadamy dane z profilu, ale sprzedawca może je zmienić
 * i wtedy trafiają WYŁĄCZNIE do regulaminu — nic nie wraca do `shops`. Nie
 * sprawdzamy prawdziwości, nie synchronizujemy, nie pilnujemy aktualności.
 * Za treść regulaminu odpowiada sprzedawca i tak jest to opisane pod formularzem.
 */
class SellerTerms
{
    /**
     * Wersja wzoru. PODBIJAĆ przy każdej zmianie treści szablonu — inaczej po
     * poprawkach prawnika nie ustalimy, kto opublikował starą wersję.
     */
    /** v2 (11.2026): formularz kontaktowy w kontakcie i celach przetwarzania (gdy włączony). */
    public const VERSION = 2;

    /**
     * Pola kreatora. Klucz => czy wymagane.
     *
     * `nip`, `phone`, `return_address` i `withdrawal_exclusions` są opcjonalne,
     * bo pustka jest tu poprawną odpowiedzią: działalność nierejestrowana nie ma
     * NIP-u (§6 ust. 2 Regulaminu Kramio), a większość sklepów nie sprzedaje
     * towarów wyłączonych z prawa odstąpienia.
     */
    public const POLA = [
        'seller_name' => true,
        'nip' => false,
        'address' => true,
        'email' => true,
        'phone' => false,
        'return_address' => false,
        'shipping_days' => true,
        'withdrawal_exclusions' => false,
    ];

    /**
     * Wartości startowe kreatora: najpierw to, co sprzedawca już raz wpisał
     * w kreatorze, potem podpowiedzi z profilu sklepu.
     *
     * @return array<string, string>
     */
    public static function defaults(Shop $shop, ?Page $page = null): array
    {
        $zapisane = is_array($page?->terms_answers) ? $page->terms_answers : [];

        $zProfilu = [
            'seller_name' => trim((string) $shop->company_name) ?: trim((string) $shop->owner?->name.' '.$shop->owner?->surname),
            'nip' => (string) $shop->nip,
            'address' => (string) $shop->addressLine(),
            'email' => (string) $shop->contact_email,
            'phone' => (string) ($shop->formattedContactPhone() ?? ''),
            'return_address' => '',
            'shipping_days' => '',
            'withdrawal_exclusions' => '',
        ];

        foreach ($zProfilu as $klucz => $wartosc) {
            $zProfilu[$klucz] = (string) ($zapisane[$klucz] ?? $wartosc);
        }

        return $zProfilu;
    }

    /**
     * Treść wzoru — HTML w konwencji podstron (h2/div/ol/ul).
     *
     * Tożsamość i kontakt biorą się z ODPOWIEDZI kreatora, ale metody dostawy
     * i płatności nadal ze sklepu: tych sprzedawca nie wpisuje ręcznie, a wzór
     * ma opisywać to, co klient realnie zobaczy w kasie.
     *
     * @param  array<string, string>  $dane
     */
    public static function render(Shop $shop, array $dane): string
    {
        return trim(view('seller.legal.templates.regulamin', [
            'shop' => $shop,
            'dane' => $dane,
        ])->render());
    }

    /**
     * Czy w podstronie stoi jeszcze nasza zaślepka („regulamin w przygotowaniu").
     * Jeśli tak — podmieniamy bez ostrzeżenia, bo nie ma czego stracić.
     */
    public static function holdsPlaceholder(?string $content): bool
    {
        $wzorzec = strip_tags((string) config('pages.regulamin.content'));
        $obecna = strip_tags((string) $content);

        return trim($obecna) === '' || trim($obecna) === trim($wzorzec);
    }
}
