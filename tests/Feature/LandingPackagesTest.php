<?php

namespace Tests\Feature;

use App\Support\PackageFeatures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cennik na stronie głównej: karta pakietu wyróżnia to, co DOCHODZI względem
 * pakietu niżej, żeby kupujący nie musiał porównywać trzech kolumn linijka po
 * linijce. Lista bierze się z `PackageFeatures::purchasable()`, więc nie może
 * rozjechać się z tym, co sklep faktycznie dostaje.
 *
 * Świadomie `purchasable()`, a nie `config('shop.packages')`: cennik zawiera
 * też presety spoza oferty (np. „Sklep dedykowany"), których na landingu być
 * NIE MA. Iteracja po surowym configu wymagałaby ich na stronie i kazałaby nam
 * pokazać kupującym pakiet, którego nie sprzedajemy.
 */
class LandingPackagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array<string, bool>> pakiet => [etykieta => czy nowa]
     */
    private function featureMap(): array
    {
        $map = [];

        foreach (PackageFeatures::landing() as $package) {
            foreach ($package['features'] as $feature) {
                $map[$package['name']][$feature['label']] = $feature['is_new'];
            }
        }

        return $map;
    }

    public function test_cheapest_package_highlights_nothing(): void
    {
        // Nie ma się do czego porównywać — inaczej cała karta byłaby pogrubiona.
        $this->assertNotContains(true, $this->featureMap()['Kram']);
    }

    public function test_middle_package_highlights_what_the_free_one_lacks(): void
    {
        $stragan = $this->featureMap()['Stragan'];

        $this->assertTrue($stragan['Integracja płatności online (własne konto Paynow)']);
        $this->assertTrue($stragan['Paczkomat, kurier i nadawanie paczek InPost — także za pobraniem']);
        $this->assertTrue($stragan['Integracja z Fakturownią']);
        $this->assertTrue($stragan['Google Analytics i Tag Manager']);
        // Wyższy limit produktów to też różnica, choć cecha ta sama.
        $this->assertTrue($stragan['Do 300 produktów']);
        // Tak samo tygodniowa pula AI — różni się liczbą, nie nazwą.
        $this->assertTrue($stragan['Korekta AI: 400 zadań tygodniowo']);

        // Wspólne z Kramem — bez wyróżnienia.
        $this->assertFalse($stragan['Własny adres, szablony i kolory sklepu']);
        $this->assertFalse($stragan['Zwroty 14 dni zgodne z prawem']);
    }

    public function test_top_package_highlights_only_what_the_middle_one_lacks(): void
    {
        $pawilon = $this->featureMap()['Pawilon'];

        $this->assertTrue($pawilon['Edycja zamówień']);
        $this->assertTrue($pawilon['Kody rabatowe w koszyku']);
        $this->assertTrue($pawilon['Wiadomości do klientów']);
        $this->assertTrue($pawilon['Do 600 produktów']);

        // Ma je już Stragan, więc w Pawilonie to nie jest nowość.
        $this->assertFalse($pawilon['Integracja płatności online (własne konto Paynow)']);
        $this->assertFalse($pawilon['Integracja z Fakturownią']);
    }

    public function test_landing_shows_shipped_features_without_a_coming_soon_badge(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Kody rabatowe w koszyku')
            ->assertSee('Wiadomości do klientów')
            ->assertSee('Zwroty 14 dni zgodne z prawem')
            // Oba moduły są na produkcji — plakietka wprowadzałaby w błąd.
            ->assertDontSee('wkrótce');
    }

    public function test_landing_explains_how_to_buy_a_package(): void
    {
        // Ceny same nie mówią, CO ZROBIĆ — ktoś, kto chce od razu Pawilon, musi
        // wiedzieć, że droga wiedzie przez darmowe konto.
        $this->get('/')
            ->assertOk()
            ->assertSee('Jak kupić pakiet?')
            ->assertSee('Załóż darmowe konto')
            // Bez cudzysłowu w asercji — Blade escapuje go na `&quot;`.
            ->assertSee('Wejdź w')
            ->assertSee('Załóż konto i wybierz pakiet')
            // Reguła zmiany pakietu wyłożona wprost, nie jako haczyk.
            ->assertSee('niewykorzystany okres wraca jako zniżka');
    }

    public function test_each_package_card_has_its_own_call_to_action(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Zacznij za darmo')       // Kram
            ->assertSee('Wybierz Stragan')
            ->assertSee('Wybierz Pawilon');
    }

    public function test_price_shows_twelve_months_struck_through_next_to_the_yearly_one(): void
    {
        // Zysk ma być widoczny bez liczenia: obok ceny rocznej stoi przekreślona
        // cena 12 miesięcy (stawka miesięczna × 12). Obie kwoty liczą się z
        // `shop.billing`, więc zmiana reguły przelicza cennik, a nie rozjeżdża go.
        $paid = (int) config('shop.billing.months_paid');
        $total = (int) config('shop.billing.months_total');

        $html = $this->get('/')->assertOk()->getContent();

        foreach (PackageFeatures::purchasable() as $package) {
            $yearly = (int) $package['price_yearly'];

            if ($yearly === 0) {
                continue;   // Kram jest darmowy — nie ma czego przekreślać
            }

            $monthly = intdiv($yearly, $paid);

            $this->assertStringContainsString('<s class="text-xl text-stone-400">'.($monthly * $total).' zł</s>', $html);
            $this->assertStringContainsString($yearly.' zł', $html);
        }

        $this->assertStringContainsString(($total - $paid).' miesiące za darmo', $html);
        // Stary opis („płacisz za 10 miesięcy, 2 gratis") zastąpiony przekreśleniem.
        $this->assertStringNotContainsString('2 gratis', $html);
    }

    public function test_features_do_not_promise_what_we_do_not_have(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Danych strukturalnych PRODUKTU (schema.org Product) nie mamy — są tylko
        // okruszki nawigacyjne. Mapa strony natomiast JEST od 2026-08-03, więc
        // dawny zakaz jej wymieniania zniknął (audyt 08.08).
        $this->assertStringNotContainsString('dane produktów dla wyszukiwarek', $html);

        // …a to, co mamy, jest wymienione po imieniu, nie jako „kolejne metody".
        $this->assertStringNotContainsString('i kolejne metody', $html);
        $this->assertStringContainsString('BLIK', $html);
        $this->assertStringContainsString('Paczkomat', $html);
        $this->assertStringContainsString('Zgodność z prawem', $html);
    }

    public function test_landing_names_what_shipped_after_the_last_rewrite(): void
    {
        // Audyt 2026-08-08: landing milczał o modułach, które od dawna działają.
        // Ten test jest zaporą — kolejne wdrożenie ma dopisać się do siatki,
        // a nie zostać w kodzie bez słowa dla kupującego.
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['mapa strony', 'Search Console', 'wydrukujesz etykietę', 'szablonów', 'Kody rabatowe', 'kartoteka', 'Edycja zamówień'] as $promise) {
            $this->assertStringContainsString($promise, $html, "Landing nie mówi o: {$promise}");
        }
    }

    public function test_gated_features_carry_the_package_badge(): void
    {
        // Kafelek nie może obiecać w darmowym Kramie czegoś płatnego — dokładnie
        // ten błąd miała karta „Wysyłka i odbiór” przed audytem. Plakietka liczy
        // się z configu, więc idzie za zmianą presetów.
        $html = $this->get('/')->assertOk()->getContent();

        $shipping = PackageFeatures::cheapestWith('courier_shipping');
        $analytics = PackageFeatures::cheapestWith('ga_analytics');

        $this->assertStringContainsString('od pakietu '.$shipping['name'], $html);
        $this->assertStringContainsString('od pakietu '.$analytics['name'], $html);
    }

    public function test_landing_states_the_downgrade_rule_honestly(): void
    {
        // „Pakiet zmienisz w każdej chwili" było nieprawdą: zejście niżej wpuszcza
        // dopiero okno `notice_days` przed końcem okresu (PackageUpgrade::downsize).
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('Pakiet zmienisz w każdej chwili', $html);
        $this->assertStringNotContainsString('zmienisz je w każdej chwili', $html);
        $this->assertStringContainsString('ostatnich '.config('shop.subscription.notice_days').' dniach', $html);

        // Rejestracja to akceptacja Regulaminu, a płatności stoją na własnej
        // umowie sprzedawcy z operatorem — „bez umów" się z tym kłóciło.
        $this->assertStringNotContainsString('Bez umów', $html);
    }

    public function test_package_cards_disclose_the_weekly_ai_pool(): void
    {
        // Limit jest realny i egzekwowany przez AiQuota — kupujący ma go widzieć
        // w cenniku, a nie odkrywać dopiero po wejściu w ścianę.
        $html = $this->get('/')->assertOk()->getContent();

        foreach (PackageFeatures::purchasable() as $package) {
            $this->assertStringContainsString(
                'Korekta AI: '.$package['entitlements']['ai_weekly_limit'].' zadań tygodniowo',
                $html,
            );
        }
    }

    public function test_returns_are_listed_in_every_package(): void
    {
        foreach ($this->featureMap() as $package => $features) {
            $this->assertArrayHasKey(
                'Zwroty 14 dni zgodne z prawem',
                $features,
                "Pakiet {$package} musi mieć obsługę zwrotów — prawo odstąpienia nie zależy od opłaty.",
            );
        }
    }
}
