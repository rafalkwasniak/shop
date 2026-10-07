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
        $ledger = app(LoyaltyLedger::class);
        $examplePoints = $ledger->pointsFromBase($shop, 100);

        return trim(view('seller.legal.templates.zasady-punktow', [
            'shop' => $shop,
            'percent' => self::percent((float) $shop->loyalty_earn_percent),
            'pointValue' => Money::pln($shop->loyaltyPointValue()),
            'examplePoints' => $examplePoints,
            'exampleValue' => Money::pln($examplePoints * $shop->loyaltyPointValue()),
            'delay' => self::days($shop->loyaltyDelayDays()),
            'validity' => $shop->loyalty_validity_months ? self::months($shop->loyalty_validity_months) : null,
            'maxRedeem' => $shop->loyalty_max_redeem_percent,
            'minRedeem' => $shop->loyalty_min_redeem_points,
        ])->render());
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
