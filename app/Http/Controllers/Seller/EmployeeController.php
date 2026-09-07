<?php

namespace App\Http\Controllers\Seller;

use App\Enums\PanelSection;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\EmployeeInviteRequest;
use App\Http\Requests\Seller\EmployeeSectionsRequest;
use App\Models\ShopEmployee;
use App\Models\User;
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
            'employees' => $allowed
                ? $shop->employees()->with('user')->orderByRaw('revoked_at is not null')->latest('id')->get()
                : collect(),
            'slotsLeft' => $allowed ? $shop->employeeSlotsLeft() : 0,
            'slots' => $allowed ? (int) $shop->entitlement('max_employees') : 0,
        ]);
    }

    public function store(EmployeeInviteRequest $request): RedirectResponse
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

        return back()->with('status', 'Dodano pracownika: '.$employee->name.' '.$employee->surname.'.');
    }

    public function update(EmployeeSectionsRequest $request, ShopEmployee $employee): RedirectResponse
    {
        $this->authorizeEmployee($request, $employee);

        $employee->update(['permissions' => $request->validated('permissions')]);

        return back()->with('status', 'Zapisano działy pracownika.');
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
