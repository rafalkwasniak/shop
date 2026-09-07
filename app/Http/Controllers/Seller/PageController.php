<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\PageRequest;
use App\Http\Requests\Seller\SellerTermsRequest;
use App\Models\Page;
use App\Support\SellerTerms;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Zarządzanie stronami tekstowymi sklepu („Informacje") — Regulamin i dowolne
 * strony sprzedawcy. Wszystko scope'owane do sklepu zalogowanego sprzedawcy.
 * Jedna wspólna kolejność (`position`) rządzi menu i stopką storefrontu.
 * Regulamin (`is_system`) jest nieusuwalny, zawsze opublikowany i ma stały tytuł
 * — można go tylko wypełnić treścią i przestawić w kolejności.
 */
class PageController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $request->user()->currentShop();

        return view('seller.pages.index', [
            'shop' => $shop,
            'pages' => $shop ? $shop->pages()->orderBy('position')->get() : collect(),
        ]);
    }

    public function create(Request $request): Renderable|RedirectResponse
    {
        if ($request->user()->currentShop() === null) {
            return redirect()->route('seller.dashboard');
        }

        return view('seller.pages.form', [
            'page' => new Page,
            'homepage' => $this->homepageInfo($request),
        ]);
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $shop = $request->user()->currentShop();

        // Nowa strona ląduje na końcu listy (najwyższa pozycja + 1).
        $position = (int) $shop->pages()->max('position') + 1;

        $shop->pages()->create($request->safe()->only('title', 'slug', 'content', 'meta_description', 'published', 'show_on_homepage') + [
            'position' => $position,
        ]);

        return redirect()->route('seller.pages.index')->with('success', 'Strona została dodana.');
    }

    public function edit(Request $request, Page $page): Renderable
    {
        $this->authorizePage($request, $page);

        return view('seller.pages.form', [
            'page' => $page,
            'homepage' => $this->homepageInfo($request),
        ]);
    }

    public function update(PageRequest $request, Page $page): RedirectResponse
    {
        $this->authorizePage($request, $page);

        if ($page->is_system) {
            // Regulamin: wolno wypełnić treść, ale tytuł jest stały, strona zostaje
            // opublikowana i nie da się jej wyróżnić na głównej (regulamin jako
            // zajawka-witryna nie ma sensu). Formularz nie pokazuje tych pól, a tu
            // i tak ich nie przyjmujemy — dwie zapory, nie jedna.
            $page->update($request->safe()->only('content', 'meta_description', 'terms_template_version'));
        } else {
            $page->update($request->safe()->only('title', 'slug', 'content', 'meta_description', 'published', 'show_on_homepage'));
        }

        return redirect()->route('seller.pages.edit', $page)->with('success', 'Zapisano zmiany.');
    }

    public function destroy(Request $request, Page $page): RedirectResponse
    {
        $this->authorizePage($request, $page);

        // Strona systemowa (Regulamin) jest nieusuwalna.
        abort_if($page->is_system, 403);

        $page->delete();

        return redirect()->route('seller.pages.index')->with('success', 'Strona została usunięta.');
    }

    /**
     * Zapis nowej kolejności (drag & drop). Przyjmuje listę ID w docelowej
     * kolejności; ustawia `position` 0,1,2… wyłącznie dla stron tego sklepu
     * (obce/nieznane ID są ignorowane). Atomowo, w jednej transakcji.
     */
    public function reorder(Request $request): JsonResponse
    {
        $shop = $request->user()->currentShop();
        abort_if($shop === null, 403);

        $ids = collect($request->input('order', []))
            ->map(fn ($id): int => (int) $id)
            ->filter();

        // Tylko strony należące do sklepu — zamyka manipulację obcymi ID.
        $owned = $shop->pages()->whereIn('id', $ids)->pluck('id')->all();

        DB::transaction(function () use ($ids, $owned, $shop): void {
            $position = 0;
            foreach ($ids as $id) {
                if (! in_array($id, $owned, true)) {
                    continue;
                }
                $shop->pages()->whereKey($id)->update(['position' => $position++]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Stan wyróżnień na stronie głównej — do podpowiedzi „zajęte X z Y" na
     * formularzu strony (bliźniak `ProductController::homepageInfo()`).
     * Liczymy FLAGĘ, nie widoczność — tak samo jak walidacja w PageRequest, żeby
     * licznik i komunikat błędu nigdy nie mówiły dwóch różnych rzeczy.
     *
     * @return array{count: int, limit: int}
     */
    private function homepageInfo(Request $request): array
    {
        $shop = $request->user()->currentShop();

        return [
            'count' => $shop ? $shop->pages()->where('show_on_homepage', true)->count() : 0,
            'limit' => (int) config('pages.homepage_promoted_limit'),
        ];
    }

    /**
     * Otwiera kreator regulaminu — pola wypełnione podpowiedziami z profilu.
     *
     * Nie blokujemy niekompletnego sklepu: brakujące dane sprzedawca wpisze
     * TUTAJ, a nie na innym ekranie, z którego musiałby wracać.
     */
    public function termsWizard(Request $request, Page $page): RedirectResponse
    {
        $this->authorizePage($request, $page);
        abort_unless($page->is_system, 404);

        return redirect()
            ->route('seller.pages.edit', $page)
            ->with('terms_wizard', SellerTerms::defaults($request->user()->currentShop(), $page));
    }

    /**
     * Wstawia wypełniony wzór do EDYTORA — bez zapisu treści.
     *
     * Podstrona systemowa jest zawsze opublikowana (`is_system`), więc zapis
     * treści oznaczałby natychmiastową publikację dokumentu w imieniu sprzedawcy,
     * zanim ten go przeczyta. Dlatego odbijamy treść przez `withInput()`:
     * formularz czyta `old('content', …)`, a publikuje dopiero „Zapisz".
     *
     * Same ODPOWIEDZI zapisujemy od razu — to pamięć kreatora, nie dokument.
     * Dzięki temu po poprawkach prawnika sprzedawca nie przepisuje wszystkiego
     * od zera. NIC z nich nie wraca do `shops`: może tu podać inny adres niż
     * w ustawieniach i to jego prawo (adres rejestrowy, zwrotów i kontaktowy
     * bywają trzema różnymi rzeczami).
     */
    public function insertTerms(SellerTermsRequest $request, Page $page): RedirectResponse
    {
        $this->authorizePage($request, $page);
        abort_unless($page->is_system, 404);

        $dane = $request->safe()->only(array_keys(SellerTerms::POLA));
        $page->forceFill(['terms_answers' => $dane])->save();

        return redirect()
            ->route('seller.pages.edit', $page)
            ->withInput([
                'content' => SellerTerms::render($request->user()->currentShop(), $dane),
                'terms_template_version' => SellerTerms::VERSION,
            ])
            ->with('success', 'Wzór wstawiony do edytora. Przeczytaj go i zapisz — dopiero zapis publikuje regulamin w Twoim sklepie.');
    }

    /**
     * Strona musi należeć do sklepu zalogowanego sprzedawcy — inaczej 404
     * (nie zdradzamy istnienia cudzych stron).
     */
    private function authorizePage(Request $request, Page $page): void
    {
        abort_if($page->shop_id !== $request->user()->currentShop()?->id, 404);
    }
}
