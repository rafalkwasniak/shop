<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Ochrona formularza kontaktowego storefrontu przed spamem — bez captchy.
 *
 * Dwa rodzaje zapór, z różną odpowiedzią dla nadawcy:
 *
 * ROBOT (`looksLikeBot`) — pole-pułapka, za szybkie wysłanie, za dużo linków.
 * Odpowiadamy CICHO: robot widzi „Dziękujemy, wiadomość wysłana" i nie ma
 * sygnału, że wpadł, więc nie próbuje innej drogi. Człowiek w te pułapki nie
 * wpada — pola nie widzi, a w 3 sekundy pytania nie napisze.
 *
 * LIMITY (`ipLimited`, `shopLimited`) — przekroczenie może dotyczyć człowieka,
 * więc tu mówimy wprost, co się stało i jak dotrzeć do sklepu inaczej.
 *
 * Progi w `config/security.php` (klucz `contact_form`).
 */
class ContactFormGuard
{
    /** Nazwa pola-pułapki. Brzmi jak pole, które robot chętnie wypełni. */
    public const HONEYPOT = 'website';

    /** Nazwa pola z podpisanym czasem wyświetlenia formularza. */
    public const TIMESTAMP = 'form_ts';

    /**
     * Zaszyfrowany znacznik czasu do ukrytego pola formularza. Szyfrowanie, nie
     * goła liczba: robot podstawiłby sobie dowolnie starą datę.
     */
    public function timestamp(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    public function looksLikeBot(Request $request): bool
    {
        if (filled($request->input(self::HONEYPOT))) {
            return true;
        }

        if ($this->secondsSinceDisplay($request) < (int) config('security.contact_form.min_seconds')) {
            return true;
        }

        return $this->linkCount((string) $request->input('message')) > (int) config('security.contact_form.max_links');
    }

    /** Czy adres IP wyczerpał któryś z limitów. Sprawdza, nie nalicza. */
    public function ipLimited(Request $request): bool
    {
        foreach ($this->ipLimits() as $key => $limit) {
            if (RateLimiter::tooManyAttempts($key.':'.$request->ip(), $limit['max_attempts'])) {
                return true;
            }
        }

        return false;
    }

    public function shopLimited(Shop $shop): bool
    {
        return RateLimiter::tooManyAttempts($this->shopKey($shop), (int) config('security.contact_form.per_shop_daily'));
    }

    /**
     * Nalicza wysłaną wiadomość. Wołane PO wysłaniu — odrzucony robot nie
     * zjada limitu, który należy się ludziom z tego samego adresu.
     */
    public function hit(Request $request, Shop $shop): void
    {
        foreach ($this->ipLimits() as $key => $limit) {
            RateLimiter::hit($key.':'.$request->ip(), $limit['decay_minutes'] * 60);
        }

        RateLimiter::hit($this->shopKey($shop), 86400);
    }

    /**
     * Brak pola albo podróbka = „wysłane natychmiast": treści nie da się
     * odszyfrować, więc tak samo jak przy pośpiechu traktujemy to jak robota.
     */
    private function secondsSinceDisplay(Request $request): int
    {
        try {
            $shown = (int) Crypt::decryptString((string) $request->input(self::TIMESTAMP));
        } catch (DecryptException) {
            return 0;
        }

        return now()->getTimestamp() - $shown;
    }

    private function linkCount(string $text): int
    {
        return preg_match_all('~https?://|www\.~i', $text);
    }

    /**
     * @return array<string, array{max_attempts: int, decay_minutes: int}>
     */
    private function ipLimits(): array
    {
        $limits = [];

        foreach ((array) config('security.contact_form.per_ip') as $limit) {
            $limits['contact-ip:'.$limit['decay_minutes']] = $limit;
        }

        return $limits;
    }

    private function shopKey(Shop $shop): string
    {
        return 'contact-shop:'.$shop->id;
    }
}
