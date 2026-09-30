<?php

namespace App\Support;

use App\Models\PackagePayment;
use App\Models\Shop;
use Illuminate\Support\Collection;

/**
 * Przekrój pieniędzy z pakietów Kramio — jedno źródło liczb dla konsoli admina
 * (dział „Pakiety") i dla pulpitu. Dwa różne ekrany podające dwie różne kwoty
 * przychodu byłyby gorsze niż brak kwoty w ogóle.
 *
 * PRZYCHÓD liczymy wyłącznie z `package_payments` ze statusem `paid` — to
 * jedyne miejsce, w którym opłata za pakiet zostawia ślad w złotówkach. Sam
 * fakt, że sklep ma pakiet Pawilon, nie znaczy, że ktoś za niego zapłacił
 * (deal, gest, konto testowe), więc nie wolno wnioskować przychodu z pakietu.
 *
 * WARTOŚĆ ROCZNA to coś innego niż przychód: patrzy w przód (ile są warte
 * biegnące abonamenty przez rok), a nie wstecz (ile realnie wpłynęło).
 */
class PackageRevenue
{
    /**
     * Wpłacone pieniądze. `paid_at` jest tu datą kasową — po niej dzielimy na
     * okresy, nie po `created_at`, bo między rozpoczęciem płatności a wpłatą
     * potrafi minąć doba (i przełom roku).
     *
     * @return array{total: float, year: float, last12m: float, count: int}
     */
    public static function revenue(): array
    {
        $paid = fn () => PackagePayment::query()->where('status', PackagePayment::STATUS_PAID);

        return [
            'total' => (float) $paid()->sum('amount'),
            'year' => (float) $paid()->whereYear('paid_at', now()->year)->sum('amount'),
            'last12m' => (float) $paid()->where('paid_at', '>=', now()->subYear())->sum('amount'),
            'count' => $paid()->count(),
        ];
    }

    /**
     * Przekrój abonamentów: ile sklepów siedzi w którym pakiecie, ile z nich
     * faktycznie płaci i ile te abonamenty są warte przez rok.
     *
     * Liczone w PHP na wczytanych sklepach, nie zapytaniem zbiorczym: „czy
     * abonament biegnie" to reguła z `Shop::subscriptionActive()` (gratis,
     * pakiet darmowy, karencja, pusta data = bezterminowo), a przepisanie jej
     * na SQL dałoby drugą, cichą definicję tego samego — i przy pierwszej
     * zmianie reguły ekran zacząłby kłamać. Kilka kolumn na tabeli sklepów to
     * przy skali platformy koszt bez znaczenia.
     *
     * @return array{shops: int, paying: int, comped: int, annualValue: float, packages: list<array{slug: string, name: string, shops: int, paying: int, annualValue: float}>}
     */
    public static function subscriptions(): array
    {
        $shops = Shop::query()
            ->select(['id', 'package', 'price_yearly', 'subscription_ends_at', 'comped', 'deletion_scheduled_at'])
            ->get();

        // Sklep zlecony do usunięcia jest już niewidoczny dla klientów i za
        // chwilę zniknie — jego cena roczna nie jest pieniędzmi, na które można
        // liczyć, więc nie wchodzi ani do „płacących", ani do wartości rocznej.
        $billable = $shops->filter(fn (Shop $shop): bool => $shop->deletion_scheduled_at === null
            && ! $shop->comped
            && $shop->priceYearly() > 0
            && $shop->subscriptionActive());

        return [
            'shops' => $shops->count(),
            'paying' => $billable->count(),
            'comped' => $shops->where('comped', true)->count(),
            'annualValue' => (float) $billable->sum(fn (Shop $shop): float => $shop->priceYearly()),
            'packages' => self::byPackage($shops, $billable),
        ];
    }

    /**
     * Rozbicie na pakiety w kolejności z cennika. Pakiety Z OFERTY bez ani
     * jednego sklepu ZOSTAJĄ na liście z zerem — pusta pozycja jest informacją
     * („nikt tego nie kupuje"), a wycięcie jej udawałoby, że pakietu nie ma
     * w ofercie.
     *
     * Presety spoza oferty (`available => false`, dziś „Sklep dedykowany")
     * pokazujemy dopiero wtedy, gdy jakiś sklep faktycznie w nich siedzi.
     * Pusty wiersz mówiłby o Kramio nieprawdę — takiego pakietu na platformie
     * nie ma, to preset wdrożenia u klienta na jego serwerze. Nie wycinamy go
     * jednak bezwarunkowo: gdyby taki sklep istniał, jego sklepy i wartość
     * roczna zniknęłyby z tabeli, a stopka „Razem" przestała się zgadzać
     * z sumą wierszy.
     *
     * @param  Collection<int, Shop>  $shops
     * @param  Collection<int, Shop>  $billable
     * @return list<array{slug: string, name: string, shops: int, paying: int, annualValue: float}>
     */
    private static function byPackage(Collection $shops, Collection $billable): array
    {
        $packages = collect(config('shop.packages', []))
            ->filter(fn (array $package, string $slug): bool => ($package['available'] ?? true) !== false
                ? true
                : $shops->where('package', $slug)->isNotEmpty())
            ->sortBy('order')
            ->map(fn (array $package, string $slug): array => [
                'slug' => $slug,
                'name' => $package['name'],
                'shops' => $shops->where('package', $slug)->count(),
                'paying' => $billable->where('package', $slug)->count(),
                'annualValue' => (float) $billable->where('package', $slug)
                    ->sum(fn (Shop $shop): float => $shop->priceYearly()),
            ]);

        return array_values($packages->all());
    }
}
