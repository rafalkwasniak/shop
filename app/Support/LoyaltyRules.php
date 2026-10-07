<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Shop;
use App\Services\LoyaltyLedger;

/**
 * Strona „Zasady punktów" — zakładana przez system, gdy sklep ma punkty, i
 * wypełniana domyślną treścią z ustawień. Dalej należy do sprzedawcy: może ją
 * edytować, ale nie usunie jej, dopóki punkty są włączone. Aktualność treści
 * po zmianie ustawień to jego obowiązek, tak jak przy Regulaminie — dajemy
 * przycisk, który wstawia świeży wzór do edytora.
 */
class LoyaltyRules
{
    public const TITLE = 'Zasady punktów';

    public const SLUG = 'zasady-punktow';

    /** Strona zasad sklepu; zakłada ją z domyślną treścią, jeśli jeszcze nie istnieje. */
    public static function ensurePage(Shop $shop): Page
    {
        $page = $shop->pages()->where('system_key', Page::LOYALTY_RULES)->first();

        if ($page !== null) {
            return $page;
        }

        $page = $shop->pages()->make([
            'title' => self::TITLE,
            'slug' => self::SLUG,
            'content' => self::render($shop),
            'position' => (int) $shop->pages()->max('position') + 1,
            'published' => true,
        ]);
        $page->system_key = Page::LOYALTY_RULES;
        $page->save();

        return $page;
    }

    /** Domyślna treść zasad z bieżących ustawień sklepu (HTML w konwencji podstron). */
    public static function render(Shop $shop): string
    {
        return trim(view('seller.legal.templates.zasady-punktow', [
            'shop' => $shop,
            'earnPhrase' => self::earnPhrase($shop),
            'redeemPhrase' => self::redeemPhrase($shop),
            'delay' => self::days($shop->loyaltyDelayDays()),
            'validity' => $shop->loyalty_validity_months ? self::months($shop->loyalty_validity_months) : null,
            'maxRedeem' => $shop->loyalty_max_redeem_percent,
            'minRedeem' => $shop->loyalty_min_redeem_points,
        ])->render());
    }

    /**
     * Stawka zbierania w punktach na złotówkę — tak, jak podaje ją konkurencja
     * („za każdą złotówkę 5 pkt"), a NIE w procentach zwrotu. Decyzja Rafała
     * 07.10: klient ma wiedzieć, ile zbiera, ale nie dostaje wyliczenia, jak
     * mały to zwrot. Bierzemy najmniejszą z kwot 1 / 10 / 100 zł, przy której
     * wychodzi pełna liczba punktów; gdy żadna, podajemy 100 zł (w dół).
     */
    public static function earnPhrase(Shop $shop): string
    {
        $percent = (int) round((float) $shop->loyalty_earn_percent * 100);
        $value = (int) round($shop->loyaltyPointValue() * 100);
        $wording = [
            1 => 'Za każdą złotówkę wydaną na produkty dostajesz',
            10 => 'Za każde 10 zł wydane na produkty dostajesz',
            100 => 'Za każde 100 zł wydane na produkty dostajesz',
        ];

        foreach ($wording as $zloty => $text) {
            $numerator = $zloty * 100 * $percent;

            if ($value > 0 && $numerator % (10000 * $value) === 0 && $numerator > 0) {
                return $text.' '.intdiv($numerator, 10000 * $value).' pkt.';
            }
        }

        return $wording[100].' '.app(LoyaltyLedger::class)->pointsFromBase($shop, 100).' pkt.';
    }

    /**
     * Przelicznik przy wydawaniu, podany na setkę punktów („100 pkt = 1,00 zł")
     * — jedyne miejsce, w którym klient zobaczy złotówki przed koszykiem.
     * Zostaje, bo zasady programu muszą mówić, ile punkty są warte; resztę
     * (procent zwrotu, wyliczenia) świadomie pomijamy.
     */
    public static function redeemPhrase(Shop $shop): string
    {
        return '100 pkt = '.Money::pln(100 * $shop->loyaltyPointValue());
    }

    /** 5 → „5%", 2,5 → „2,5%". */
    public static function percent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',').'%';
    }

    public static function days(int $days): string
    {
        return $days.' '.($days === 1 ? 'dzień' : 'dni');
    }

    public static function months(int $months): string
    {
        $lastDigit = $months % 10;
        $lastTwo = $months % 100;

        $word = match (true) {
            $months === 1 => 'miesiąc',
            $lastDigit >= 2 && $lastDigit <= 4 && ($lastTwo < 12 || $lastTwo > 14) => 'miesiące',
            default => 'miesięcy',
        };

        return $months.' '.$word;
    }
}
