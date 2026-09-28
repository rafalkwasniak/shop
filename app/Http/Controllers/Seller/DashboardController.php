<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PanelSection;
use App\Http\Controllers\Controller;
use App\Services\AiQuota;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Renderable
    {
        $shop = $request->user()->currentShop();
        $productCount = $shop ? $shop->products()->count() : 0;
        $activeProductCount = $shop ? $shop->products()->where('is_active', true)->count() : 0;

        // Sprzedaż z ostatnich 30 dni — realne liczby zamiast zer na kafelkach.
        // Anulowane zamówienia nie liczą się ani do sztuk, ani do przychodu.
        // Wyświetlenia/ruch: brak trackingu → kafelek świadomie usunięty z Pulpitu
        // (wróci przy module analityki), zamiast straszyć placeholderem „wkrótce".
        $recentOrders = $shop
            ? $shop->orders()
                ->where('created_at', '>=', now()->subDays(30))
                ->countedAsSale()
                ->get(['id', 'total_gross'])
            : collect();

        $orderCount = $recentOrders->count();
        $revenue = (float) $recentOrders->sum(fn ($order) => (float) $order->total_gross);

        // Ścieżka „kim jesteś → jak wyglądasz → idź na żywo → daj się kupić".
        // Kolejność = realne kroki do pokazania sklepu klientom; widoczny produkt
        // publikuje sklep — dlatego liczymy AKTYWNE produkty, nie wszystkie (ukryty
        // produkt nie publikuje). Ostatni krok to zdolność przyjmowania zamówień:
        // sklep bywa opublikowany i pełen produktów, a jednocześnie bez przycisku
        // „Do koszyka", bo brakuje metody dostawy albo zapłaty. Ten stan wygląda na
        // usterkę, więc musi być widoczny tam, gdzie sprzedawca zaczyna dzień.
        // Każdy krok prowadzi do konkretnej sekcji (kotwica). Liczone z danych.
        $steps = $shop && ! $request->user()->isEmployee() ? [
            ['label' => 'Dane sklepu', 'desc' => 'Adres prowadzenia działalności.', 'done' => $shop->addressComplete(), 'route' => 'seller.shop.edit', 'anchor' => '#adres'],
            ['label' => 'Dane kontaktowe', 'desc' => 'E-mail i telefon dla klientów.', 'done' => $shop->contactComplete(), 'route' => 'seller.shop.edit', 'anchor' => '#dane-kontaktowe'],
            ['label' => 'Dane firmowe', 'desc' => 'Nazwa firmy i NIP.', 'done' => filled($shop->nip), 'route' => 'seller.shop.edit', 'anchor' => '#dane-firmowe'],
            ['label' => 'O sklepie', 'desc' => 'Krótko o tym, co sprzedajesz.', 'done' => filled($shop->description), 'route' => 'seller.shop.edit', 'anchor' => '#dane-podstawowe'],
            ['label' => 'Logo sklepu', 'desc' => 'Wizytówka Twojej marki.', 'done' => filled($shop->logo_path), 'route' => 'seller.appearance.edit', 'anchor' => '#logo'],
            ['label' => 'Widoczny produkt', 'desc' => 'Dodaj produkt i ustaw go jako aktywny — wtedy sklep staje się widoczny.', 'done' => $activeProductCount > 0, 'route' => 'seller.products.create', 'anchor' => ''],
            ['label' => 'Dostawa i płatność', 'desc' => 'Włącz choć jeden sposób dostawy i zapłaty — bez nich sklep pokazuje ofertę, ale nie ma przycisku „Do koszyka".', 'done' => $shop->acceptsOrders(), 'route' => 'seller.settings.edit', 'anchor' => ''],
        ] : [];

        return view('seller.dashboard', [
            'shop' => $shop,
            // Pulpit jest w dużej części ekranem WŁAŚCICIELA: lista kroków
            // uruchomienia, pakiet, przychód. Widok chowa te części, zamiast
            // pokazywać pracownikowi odnośniki kończące się 403.
            'isEmployee' => $request->user()->isEmployee(),
            'canSeeOrders' => $request->user()->canAccess(PanelSection::Orders),
            // Wykorzystanie AI — ten sam licznik co na „Mój pakiet", żeby oba
            // ekrany mówiły to samo. Tygodniowa pula zadań, nie wywołań modelu.
            'aiUsed' => $shop ? app(AiQuota::class)->used($shop) : 0,
            'aiLimit' => $shop ? (int) $shop->entitlement('ai_weekly_limit') : 0,
            'steps' => $steps,
            // „Co dalej?" musi umieć powiedzieć o braku kasy wprost. Bez tego
            // ramka mówi „sklep jest widoczny, uzupełnij pozostałe kroki" sklepowi,
            // w którym nikt nie jest w stanie nic kupić — a to jest nieprawda
            // w najdroższym możliwym miejscu.
            'acceptsOrders' => $shop?->acceptsOrders() ?? false,
            'productCount' => $productCount,
            'activeProductCount' => $activeProductCount,
            'orderCount' => $orderCount,
            'revenue' => $revenue,
            'unseenOrders' => (int) ($shop?->unseen_orders_count ?? 0),
            'done' => collect($steps)->where('done', true)->count(),
            'total' => count($steps),
        ]);
    }
}
