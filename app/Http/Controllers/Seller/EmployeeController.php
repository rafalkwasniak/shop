<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PanelSection;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\EmployeeInviteRequest;
use App\Http\Requests\Seller\EmployeeSectionsRequest;
use App\Models\Shop;
use App\Models\ShopEmployee;
use App\Models\User;
use App\Services\EmployeeInvitationMailer;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * „Pracownicy" — ekran właściciela do wpuszczania innych osób do swojego panelu.
 *
 * Cała trasa stoi za `role:seller`: zarządzanie ludźmi nie jest działem, który
 * sprzedawca może komuś oddać. Pracownik z kompletem uprawnień nadal nie zaprosi
 * kolejnego pracownika ani nie odbierze dostępu sobie czy komuś innemu.
 */
class EmployeeController extends Controller
{
    public function index(Request $request): Renderable
    {
        $shop = $request->user()->currentShop();
        $allowed = (bool) $shop?->allowsEmployees();

        return view('seller.employees.index', [
            'shop' => $shop,
            'allowed' => $allowed,
            'sections' => PanelSection::cases(),
            // Odebrani na końcu, reszta od najnowszych: lista ma zaczynać się od
            // ludzi, którzy dziś pracują.
            // Lista widoczna TAKŻE przy zablokowanej funkcji: po zejściu z
            // pakietu dostępy są wygaszone, ale ludzie zostają — właściciel
            // musi widzieć, kogo to dotyczy i komu ewentualnie odebrać dostęp
            // na stałe. Zachęta obok tłumaczy, dlaczego nikt nie wejdzie.
            'employees' => $shop
                ? $shop->employees()->with('user')->orderByRaw('revoked_at is not null')->latest('id')->get()
                : collect(),
            'slotsLeft' => $allowed ? $shop->employeeSlotsLeft() : 0,
            'slots' => $allowed ? (int) $shop->entitlement('max_employees') : 0,
        ]);
    }

    /**
     * Formularz zaproszenia — OSOBNA STRONA, jak przy wiadomościach, kodach
     * rabatowych i produktach. Dodawanie z bocznej kolumny listy było jedynym
     * takim miejscem w panelu; spójność wygrywa z jednym kliknięciem mniej.
     */
    public function create(Request $request): Renderable|RedirectResponse
    {
        $shop = $this->shopWithEmployees($request);

        // Brak miejsc odsyłamy NA LISTĘ, a nie pokazujemy formularza, który przy
        // zapisie i tak odmówi. Lista mówi wtedy wprost, ile miejsc jest zajętych
        // i co z tym zrobić.
        if ($shop->employeeSlotsLeft() < 1) {
            return redirect()->route('seller.employees.index')
                ->with('error', 'Nie masz wolnych miejsc na pracowników w tym pakiecie.');
        }

        return view('seller.employees.form', [
            'shop' => $shop,
            'employee' => null,
            'sections' => PanelSection::cases(),
            'slotsLeft' => $shop->employeeSlotsLeft(),
        ]);
    }

    public function edit(Request $request, ShopEmployee $employee): Renderable
    {
        $this->authorizeEmployee($request, $employee);

        return view('seller.employees.form', [
            'shop' => $request->user()->currentShop(),
            'employee' => $employee->load('user'),
            'sections' => PanelSection::cases(),
            'slotsLeft' => $request->user()->currentShop()->employeeSlotsLeft(),
        ]);
    }

    public function store(EmployeeInviteRequest $request, EmployeeInvitationMailer $mailer): RedirectResponse
    {
        $shop = $request->user()->currentShop();

        if (! $shop->allowsEmployees()) {
            abort(403);
        }

        // Limit sprawdzany TU, a nie tylko w widoku: formularz mógł zostać
        // otwarty, gdy miejsce jeszcze było. Bez tego dwie karty przeglądarki
        // wystarczą, żeby przekroczyć pakiet.
        if ($shop->employeeSlotsLeft() < 1) {
            return back()->with('error', 'Nie masz wolnych miejsc na pracowników w tym pakiecie.');
        }

        $employee = User::create([
            'name' => $request->validated('name'),
            'surname' => $request->validated('surname'),
            'email' => $request->validated('email'),
            // Hasło losowe, NIE wybrane przez właściciela. Gdyby je znał, zapis
            // „kto zmienił status zamówienia" przestałby cokolwiek znaczyć.
            // Kolumna jest NOT NULL, więc pustej wstawić się nie da; znacznikiem
            // „nie aktywowano" jest `email_verified_at`, tak jak u sprzedawcy.
            'password' => Str::password(32),
        ]);

        $employee->forceFill([
            'role' => UserRole::Employee,
            'email_verified_at' => null,
        ])->save();

        // `user_id` NIE jest masowo przypisywalne i ma takie zostać: to jedyne
        // pole, które rozstrzyga, czyje konto dostaje dostęp do sklepu. Ustawiamy
        // je wprost, z konta założonego linijkę wyżej — nigdy z żądania.
        $membership = new ShopEmployee([
            'permissions' => $request->validated('permissions'),
            'invited_by' => $request->user()->getKey(),
            'invited_at' => now(),
        ]);
        $membership->user_id = $employee->getKey();

        $shop->employees()->save($membership);

        // Mail ląduje w outboxie, wysyła go cron — jak każdy inny w Kramio.
        $mailer->send($membership);

        return redirect()->route('seller.employees.index')
            ->with('status', 'Zaproszenie dla '.$employee->name.' poszło na adres '.$employee->email.'.');
    }

    public function update(EmployeeSectionsRequest $request, ShopEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        $employee->update(['permissions' => $request->validated('permissions')]);

        return redirect()->route('seller.employees.index')
            ->with('status', 'Zapisano działy pracownika.');
    }

    /**
     * Ponowne wysłanie zaproszenia — link żyje 7 dni, a maile bywają przeoczone.
     *
     * Tylko dla zaproszeń CZEKAJĄCYCH. Osobie, która już ustawiła hasło, nowy
     * link do jego ustawienia byłby drogą do przejęcia konta przez kogoś, kto
     * ma dostęp do jej skrzynki — a do zmiany hasła służy odzyskiwanie hasła.
     */
    public function resend(Request $request, ShopEmployee $employee, EmployeeInvitationMailer $mailer): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        if ($employee->accepted_at !== null || $employee->revoked_at !== null) {
            return back()->with('error', 'To zaproszenie nie czeka już na odpowiedź.');
        }

        $mailer->send($employee);

        return back()->with('status', 'Zaproszenie wysłane ponownie na '.$employee->user->email.'.');
    }

    /**
     * Odebranie dostępu. Wiersz ZOSTAJE — patrz komentarz w migracji: to jedyny
     * ślad, kto i kiedy miał dostęp do danych osobowych klientów sklepu.
     */
    public function revoke(Request $request, ShopEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        $employee->update(['revoked_at' => now()]);

        return back()->with('status', 'Odebrano dostęp. Pracownik odbije się przy najbliższym kliknięciu.');
    }

    /**
     * Przywrócenie dostępu osobie, której go wcześniej odebrano — bez zakładania
     * konta od nowa i bez ponownego ustawiania hasła.
     */
    public function restore(Request $request, ShopEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        if ($request->user()->currentShop()->employeeSlotsLeft() < 1) {
            return back()->with('error', 'Nie masz wolnych miejsc na pracowników w tym pakiecie.');
        }

        $employee->update(['revoked_at' => null]);

        return back()->with('status', 'Przywrócono dostęp.');
    }

    /**
     * Sklep właściciela z potwierdzonym prawem do kont pracowniczych.
     *
     * 403, a nie zachęta do zakupu: na ekran formularza wchodzi się z listy, a
     * tam przy braku uprawnienia nie ma przycisku. Kto tu trafił mimo to, wpisał
     * adres z ręki.
     */
    private function shopWithEmployees(Request $request): Shop
    {
        $shop = $request->user()->currentShop();

        abort_unless((bool) $shop?->allowsEmployees(), 403);

        return $shop;
    }

    /**
     * Członkostwo musi należeć do sklepu zalogowanego właściciela.
     *
     * 404, nie 403: cudze zatrudnienie nie jest „czymś, czego ci nie wolno" —
     * dla tego sprzedawcy ono nie istnieje i nie ma powodu potwierdzać, że
     * istnieje gdzie indziej. To odwrotność bram działów, gdzie 403 jest na
     * miejscu, bo pracownik wie, że dany ekran w jego sklepie jest.
     */
    private function authorizeEmployee(Request $request, ShopEmployee $employee): void
    {
        abort_if($employee->shop_id !== $request->user()->currentShop()?->getKey(), 404);
    }
}
