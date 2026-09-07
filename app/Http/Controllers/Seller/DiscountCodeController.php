<?php

namespace App\Http\Controllers\Seller;

use App\Enums\DiscountStatus;
use App\Http\Controllers\Controller;
use App\Models\DiscountCode;
use App\Models\Shop;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Kody rabatowe sklepu. Funkcja płatna (uprawnienie `discount_codes`, pakiet
 * Pawilon) — stronę widzą wszyscy, ale bez uprawnienia dostają zachętę zamiast
 * narzędzia (ten sam wzorzec co pusta strona Integracji w Kramie).
 *
 * Liczba użyć kodu to policzone zamówienia — dociągamy ją JEDNYM zapytaniem
 * przez `withCount`, żeby lista nie robiła zapytania na wiersz.
 */
class DiscountCodeController extends Controller
{
    /**
     * Filtry listy: klucz w URL (po polsku) → etykieta zakładki.
     */
    private const FILTERS = [
        'wszystkie' => 'Wszystkie',
        'aktywne' => 'Aktywne',
        'nieaktywne' => 'Nieaktywne',
    ];

    /** Ile kodów na stronę. Kafelek kodu jest wysoki (dane + akcje), więc 10
     *  mieści się na ekranie bez przewijania w nieskończoność. */
    private const PER_PAGE = 10;

    public function index(Request $request): Renderable
    {
        $shop = $request->user()->currentShop();
        $allowed = (bool) $shop?->entitlement('discount_codes');

        $filter = $request->query('stan');
        $filter = is_string($filter) && array_key_exists($filter, self::FILTERS) ? $filter : 'wszystkie';
        $search = trim((string) $request->query('szukaj', ''));

        $matching = $allowed ? $this->matchingCodes($shop, $search) : collect();

        return view('seller.discounts.index', [
            'shop' => $shop,
            'allowed' => $allowed,
            // Ile kodów ma sklep W OGÓLE — odróżnia „nic nie masz" od „nic nie
            // pasuje do filtrów", a to dwa różne komunikaty.
            'total' => $allowed ? $shop->discountCodes()->count() : 0,
            'filter' => $filter,
            'filters' => self::FILTERS,
            'search' => $search,
            'codes' => $this->paginate($request, $this->applyFilter($matching, $filter)),
            'listQuery' => $this->listQuery($request),
        ]);
    }

    public function create(Request $request): Renderable
    {
        $shop = $this->allowedShop($request);

        return view('seller.discounts.form', [
            'shop' => $shop,
            'code' => null,
            'prefill' => $this->prefillFrom($request, $shop),
            // Nowy kod ląduje na początku listy, więc wracamy na stronę pierwszą
            // bez filtrów — inaczej aktywny widok mógłby go od razu ukryć.
            'listQuery' => [],
        ]);
    }

    public function edit(Request $request, DiscountCode $discountCode): Renderable
    {
        $shop = $this->allowedShop($request);
        $this->authorizeCode($shop, $discountCode);

        return view('seller.discounts.form', [
            'shop' => $shop,
            'code' => $discountCode,
            'prefill' => [],
            // Kontekst listy (widok + szukanie + strona) — po zapisie wracamy
            // dokładnie tam, skąd sprzedawca wszedł w edycję.
            'listQuery' => $this->listQuery($request),
        ]);
    }

    /**
     * Kontekst listy z query stringa: aktywny widok, szukana fraza i strona.
     *
     * @return array<string, string|int>
     */
    private function listQuery(Request $request): array
    {
        $filter = (string) $request->query('stan', '');
        $page = (int) $request->query('page', 1);

        return array_filter([
            'stan' => array_key_exists($filter, self::FILTERS) && $filter !== 'wszystkie' ? $filter : null,
            'szukaj' => trim((string) $request->query('szukaj', '')) ?: null,
            'page' => $page > 1 ? $page : null,
        ]);
    }

    /**
     * Wstępne ustawienia formularza przyniesione z innego miejsca panelu —
     * dziś: „wystaw kod dla klienta" ze szczegółów zamówienia (rekompensata po
     * wpadce albo podziękowanie za zakupy).
     *
     * Kod rekompensaty domyślnie jest JEDNORAZOWY: to prezent dla jednej osoby,
     * a nie promocja. Gdy zamówienie było gościnne (bez konta), imienny kod nie
     * ma do kogo się przypiąć — zostaje jednorazowy kod ogólny, który sprzedawca
     * wysyła prywatnie.
     *
     * @return array<string, mixed>
     */
    private function prefillFrom(Request $request, Shop $shop): array
    {
        $prefill = [];

        $customerId = (int) $request->query('klient');
        if ($customerId > 0 && $shop->customers()->whereKey($customerId)->exists()) {
            $prefill['customer_id'] = $customerId;
        }

        if ($prefill !== [] || $request->boolean('jednorazowy')) {
            $prefill['uses_mode'] = 'jednorazowy';
        }

        return $prefill;
    }

    /**
     * Włącz/wyłącz jednym kliknięciem z listy. Wyłączony kod zostaje ze swoją
     * historią — to bezpieczniejszy odruch niż kasowanie, więc ma być łatwiejszy.
     */
    public function toggle(Request $request, DiscountCode $discountCode): RedirectResponse
    {
        $shop = $this->allowedShop($request);
        $this->authorizeCode($shop, $discountCode);

        $discountCode->update(['is_active' => ! $discountCode->is_active]);

        return back()->with('success', $discountCode->is_active
            ? 'Kod „'.$discountCode->code.'" jest znów aktywny.'
            : 'Kod „'.$discountCode->code.'" został wyłączony.');
    }

    public function destroy(Request $request, DiscountCode $discountCode): RedirectResponse
    {
        $shop = $this->allowedShop($request);
        $this->authorizeCode($shop, $discountCode);

        $code = $discountCode->code;
        // Zamówienia trzymają MIGAWKĘ kodu i kwoty, więc skasowanie kodu nie
        // zaciera historii — relacja gaśnie (nullOnDelete), zapis zostaje.
        $discountCode->delete();

        return redirect()->route('seller.discounts.index', $this->listQuery($request))
            ->with('success', 'Kod „'.$code.'" został usunięty.');
    }

    /**
     * Kody sklepu pasujące do szukanej frazy — po samym kodzie, po nazwie
     * produktu, którego dotyczy, i po kliencie, do którego jest przypisany
     * (imię, nazwisko lub e-mail, bo klientów najczęściej rozpoznaje się po nim).
     *
     * @return \Illuminate\Support\Collection<int, DiscountCode>
     */
    private function matchingCodes(Shop $shop, string $search): \Illuminate\Support\Collection
    {
        $query = $shop->discountCodes()
            ->with('product', 'customer')
            ->withCount(['orders as used_count' => fn ($q) => $q->countedAsSale()]);

        if ($search !== '') {
            $like = '%'.$search.'%';

            $query->where(function ($outer) use ($like) {
                $outer->where('code', 'like', $like)
                    ->orWhereHas('product', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('customer', fn ($q) => $q
                        ->where('name', 'like', $like)
                        ->orWhere('surname', 'like', $like)
                        ->orWhere('email', 'like', $like));
            });
        }

        return $query->get();
    }

    /**
     * Stronicowanie po stronie PHP. Stan kodu jest WYLICZANY (daty, pula użyć),
     * więc filtr musi zadziałać zanim potniemy listę na strony — a SQL nie zna
     * pojęcia „wyczerpany". Przy skali butikowej (dziesiątki, nie miliony kodów)
     * to jedno pobranie zamiast dublowania logiki stanu w zapytaniu.
     *
     * @param  \Illuminate\Support\Collection<int, DiscountCode>  $codes
     * @return LengthAwarePaginator<int, DiscountCode>
     */
    private function paginate(Request $request, \Illuminate\Support\Collection $codes): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $codes->forPage($page, self::PER_PAGE)->values(),
            $codes->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DiscountCode>  $codes
     * @return \Illuminate\Support\Collection<int, DiscountCode>
     */
    private function applyFilter(\Illuminate\Support\Collection $codes, string $filter): \Illuminate\Support\Collection
    {
        return match ($filter) {
            'aktywne' => $codes->filter(fn (DiscountCode $code) => $code->status() === DiscountStatus::Active)->values(),
            'nieaktywne' => $codes->filter(fn (DiscountCode $code) => $code->status() !== DiscountStatus::Active)->values(),
            default => $codes,
        };
    }

    /**
     * Sklep sprzedawcy z potwierdzonym uprawnieniem do kodów. Lista pokazuje
     * zachętę, ale samo NARZĘDZIE bez uprawnienia jest zamknięte — inaczej
     * bramka byłaby tylko kosmetyką widoku.
     */
    private function allowedShop(Request $request): Shop
    {
        $shop = $request->user()->currentShop();

        abort_if($shop === null, 404);
        abort_unless((bool) $shop->entitlement('discount_codes'), 403);

        return $shop;
    }

    /**
     * Cudzy kod nie istnieje z punktu widzenia tego sprzedawcy.
     */
    private function authorizeCode(Shop $shop, DiscountCode $code): void
    {
        abort_unless($code->shop_id === $shop->id, 404);
    }
}
