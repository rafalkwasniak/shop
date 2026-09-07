<?php

namespace App\Http\Controllers\Administrator;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shop;
use App\Support\OrderAttention;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Konsola admina — zamówienia CAŁEJ platformy.
 *
 * Świadomie NIE jest drugim panelem sprzedawcy: sprzedawca ma własną listę i
 * własną kartę zamówienia, i to on nimi steruje. Tutaj patrzymy poprzecznie —
 * na to, czego z jednego sklepu nie widać, oraz na jedną konkretną potrzebę
 * wsparcia: sprzedawca dzwoni „nie widzę zamówienia z wczoraj", a admin musi
 * móc je znaleźć po numerze albo mailu klienta.
 *
 * TYLKO DO ODCZYTU (decyzja Rafała 2026-08-11). Zmiana statusu z konsoli
 * wysłałaby klientowi maila, którego sprzedawca nie zamówił i o którym by nie
 * wiedział, a przy sporze „kto anulował to zamówienie" nie dałoby się wskazać
 * winnego. Wsparcie polega na powiedzeniu sprzedawcy, co ma kliknąć.
 */
class OrderController extends Controller
{
    /** Okresy do filtra — etykieta i liczba dni wstecz (null = bez ograniczenia). */
    private const PERIODS = [
        '7' => ['label' => 'Ostatnie 7 dni', 'days' => 7],
        '30' => ['label' => 'Ostatnie 30 dni', 'days' => 30],
        '90' => ['label' => 'Ostatnie 90 dni', 'days' => 90],
        '' => ['label' => 'Cała historia', 'days' => null],
    ];

    public function index(Request $request): Renderable
    {
        $filters = [
            'q' => trim((string) $request->query('szukaj', '')),
            'status' => (string) $request->query('status', ''),
            'shop' => (string) $request->query('sklep', ''),
            // Domyślnie 30 dni: lista wsparcia ma pokazywać to, co świeże, a nie
            // całą historię platformy. Pełny zakres jest o jeden wybór dalej.
            'period' => $request->has('okres') ? (string) $request->query('okres') : '30',
        ];

        $sum = $this->query($filters)->countedAsSale();

        // Statystyki liczone z CAŁEGO przefiltrowanego zbioru, nie z bieżącej
        // strony — inaczej „sprzedaż" znaczyłaby „sprzedaż z dziesięciu wierszy,
        // które akurat widzę". Anulowane odpadają: to karta sprzedaży, a
        // anulowane zamówienie zakupem nie jest, mimo że LISTA je pokazuje.
        $count = (clone $sum)->count();
        $revenue = (float) (clone $sum)->sum('total_gross');

        return view('administrator.orders.index', [
            'orders' => $this->query($filters)
                ->with('shop')
                ->latest('created_at')
                ->orderByDesc('id')  // stabilny tie-break przy równych sekundach
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'stats' => [
                'orders' => $count,
                'revenue' => $revenue,
                // Średnia z zamówień LICZONYCH JAKO SPRZEDAŻ, więc anulowane nie
                // zaniżają koszyka do liczby, której nikt nigdy nie zapłacił.
                'basket' => $count > 0 ? $revenue / $count : 0.0,
                'shops' => (clone $sum)->distinct()->count('shop_id'),
            ],
            // Świadomie POZA filtrami: „co się pali" ma być tą samą odpowiedzią
            // niezależnie od tego, czego akurat szukasz na liście obok.
            'attention' => OrderAttention::groups(),
            'statuses' => OrderStatus::cases(),
            'shops' => Shop::query()->orderBy('name')->get(['id', 'name']),
            'periods' => self::PERIODS,
            'hasFilters' => $filters['q'] !== '' || $filters['status'] !== ''
                || $filters['shop'] !== '' || $filters['period'] !== '30',
        ]);
    }

    /**
     * Karta zamówienia — PODGLĄD, bez jednej akcji zmieniającej cokolwiek.
     *
     * Po to, żeby wsparcie mogło odpowiedzieć na pytanie „co jest w tym
     * zamówieniu i na czym stoi", nie prosząc sprzedawcy o zrzut ekranu.
     *
     * `statusEvents` wczytujemy razem z resztą: historia statusów to jedyne
     * miejsce, z którego widać, KIEDY zamówienie utknęło — sama data utworzenia
     * i bieżący status tego nie mówią.
     */
    public function show(Order $order): Renderable
    {
        return view('administrator.orders.show', [
            'order' => $order->load([
                'items',
                'shop.owner',
                'statusEvents' => fn ($query) => $query->with('author')->oldest('created_at'),
            ]),
        ]);
    }

    /**
     * @param  array{q: string, status: string, shop: string, period: string}  $filters
     * @return Builder<Order>
     */
    private function query(array $filters): Builder
    {
        return Order::query()
            ->when($filters['q'] !== '', function (Builder $query) use ($filters): void {
                $like = '%'.$filters['q'].'%';

                // Jedno pole na wszystko, czym wsparcie dysponuje przez telefon:
                // numer zamówienia, mail albo nazwisko klienta, nazwa sklepu.
                // Rozbicie na osobne filtry kazałoby zgadywać, w które wpisać.
                $query->where(function (Builder $inner) use ($like): void {
                    $inner->where('number', 'like', $like)
                        ->orWhere('buyer_email', 'like', $like)
                        ->orWhere('buyer_surname', 'like', $like)
                        ->orWhere('buyer_name', 'like', $like)
                        ->orWhereHas('shop', fn (Builder $shop) => $shop->where('name', 'like', $like));
                });
            })
            ->when($filters['status'] !== '', fn (Builder $query) => $query->where('status', $filters['status']))
            ->when($filters['shop'] !== '', fn (Builder $query) => $query->where('shop_id', $filters['shop']))
            ->when(
                self::PERIODS[$filters['period']]['days'] ?? null,
                fn (Builder $query, int $days) => $query->where('created_at', '>=', now()->subDays($days))
            );
    }
}
