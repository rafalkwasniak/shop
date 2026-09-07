<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\CustomerDirectory;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Kartoteka klientów sklepu. Dostępna we WSZYSTKICH pakietach — to podstawowe
 * narzędzie obsługi sprzedaży (jak zakładka Zamówienia), a nie funkcja premium.
 *
 * Klientem jest tu każdy, kto kupił: konta i goście razem, sklejeni po adresie
 * e-mail. Dlatego identyfikatorem w adresie strony jest e-mail, a nie `id` —
 * gość żadnego `id` nie ma.
 */
class CustomerController extends Controller
{
    /**
     * Sposoby sortowania: klucz w URL (po polsku) → etykieta zakładki.
     */
    private const SORTS = [
        'ostatnie' => 'Ostatni zakup',
        'wydatki' => 'Wydatki',
        'zamowienia' => 'Liczba zamówień',
        'nazwa' => 'Nazwisko',
    ];

    private const PER_PAGE = 20;

    /** Zamówień na stronę na karcie klienta — wiersz jest niski, więc mieści się więcej. */
    private const ORDERS_PER_PAGE = 15;

    public function index(Request $request, CustomerDirectory $directory): Renderable
    {
        $shop = $request->user()->currentShop();

        $sort = $request->query('sortuj');
        $sort = is_string($sort) && array_key_exists($sort, self::SORTS) ? $sort : 'ostatnie';
        $search = trim((string) $request->query('szukaj', ''));

        $filters = [
            'konto' => $this->tristate($request->query('konto')),
            'zgoda' => $this->tristate($request->query('zgoda')),
        ];

        $rows = $shop !== null ? $directory->search($shop, $search, $sort, $filters) : collect();

        return view('seller.customers.index', [
            'shop' => $shop,
            // Ilu klientów sklep ma W OGÓLE — odróżnia „nikt jeszcze nie kupił"
            // od „nic nie pasuje do filtrów", a to dwa różne komunikaty.
            'total' => $shop !== null ? $directory->all($shop)->count() : 0,
            'customers' => $this->paginate($request, $rows),
            'sort' => $sort,
            'sorts' => self::SORTS,
            'search' => $search,
            'filters' => $filters,
            // Czy cokolwiek zawężamy — od tego zależy, czy pokazać „Wyczyść".
            'filtered' => $search !== '' || $filters['konto'] !== null || $filters['zgoda'] !== null,
            // Podsumowanie WYŚWIETLONEGO zbioru (po filtrach), liczone ze
            // wszystkich stron — tak jak kafelki sprzedaży w Zamówieniach.
            'summary' => [
                'customers' => $rows->count(),
                'orders' => (int) $rows->sum('orders_count'),
                'spent' => round((float) $rows->sum('total_spent'), 2),
            ],
        ]);
    }

    public function show(Request $request, string $email, CustomerDirectory $directory): Renderable
    {
        $shop = $request->user()->currentShop();
        $profile = $shop !== null ? $directory->profile($shop, $email) : null;

        // Brak profilu = ten adres nic w tym sklepie nie kupił. 404 zamiast
        // pustej strony — i zarazem gwarancja, że nie da się podejrzeć klienta
        // z cudzego sklepu, bo `profile()` scope'uje po sklepie.
        abort_if($profile === null, 404);

        return view('seller.customers.show', [
            'shop' => $shop,
            'customer' => $profile,
            // Zamówienia stronicowane osobno — stały klient może ich mieć
            // dziesiątki, a karta ma pozostać czytelna.
            'orders' => $this->paginate($request, collect($profile['orders']), self::ORDERS_PER_PAGE),
        ]);
    }

    /**
     * Filtr trójstanowy z URL: „1" → tak, „0" → nie, brak/cokolwiek innego →
     * bez znaczenia. Bez tego nie dałoby się wskazać klientów BEZ konta —
     * „0" i „brak parametru" znaczyłyby to samo.
     */
    private function tristate(mixed $value): ?bool
    {
        return match ((string) $value) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * Paginacja kolekcji (kartoteka powstaje z agregacji zamówień, nie z
     * jednej tabeli) — ten sam wzorzec co lista kodów rabatowych.
     *
     * @param  Collection<int, mixed>  $rows
     * @return LengthAwarePaginator<int, mixed>
     */
    private function paginate(Request $request, Collection $rows, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= self::PER_PAGE;
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
