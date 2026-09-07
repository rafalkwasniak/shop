<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\Product;
use App\Models\ReservedSlug;
use App\Models\Shop;
use App\Models\User;
use App\Support\Vocative;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Usuwanie sklepu — jeden silnik dla obu dróg: admin kasuje z konsoli od razu,
 * sprzedawca zleca usunięcie z karencją, a po jej upływie `shops:purge` woła
 * dokładnie tę samą metodę.
 *
 * SKLEP I KONTO GINĄ RAZEM (decyzja Rafała 2026-08-04): rejestracja tworzy
 * sklep, więc konto bez sklepu jest martwe. Dlatego `erase()` kasuje WŁAŚCICIELA
 * i pozwala kaskadzie FK (`users → shops → wszystko najemcze`) zdjąć resztę
 * jednym ruchem.
 *
 * Trzy rzeczy, których kaskada bazy NIE zrobi i dlatego są tu ręcznie:
 *  1. PLIKI — kasowanie w bazie nie odpala hooka `ProductImage::deleting`,
 *     więc katalogi zbieramy przed usunięciem i czyścimy PO commicie (rollback
 *     transakcji nie przywróci skasowanego pliku).
 *  2. TABELE BEZ FK — `email_messages.shop_id` to goły indeks, a `sessions`
 *     i `password_reset_tokens` nie znają właściciela.
 *  3. KWARANTANNA ADRESU — slug musi przeżyć sklep, żeby nikt nie przejął
 *     subdomeny, do której prowadzą stare linki i maile do klientów.
 */
class ShopEraser
{
    /**
     * Zlecenie usunięcia przez sprzedawcę. Sklep gaśnie NATYCHMIAST (bramka w
     * `ResolveShop`), a właściwe kasowanie robi `shops:purge` po karencji.
     * Gaszenie od razu zamyka pytanie „co, jeśli w karencji wpłynie zamówienie":
     * nie wpłynie, bo storefront nie odpowiada.
     *
     * Zwraca termin, żeby wołający mógł go od razu pokazać.
     */
    public function schedule(Shop $shop): Carbon
    {
        $due = Carbon::now()->addDays((int) config('shop.deletion.grace_days'));

        // Kolumna celowo nie jest mass-assignable — termin ustawia tylko ten serwis.
        $shop->forceFill(['deletion_scheduled_at' => $due])->save();

        $this->mailScheduled($shop);

        return $due;
    }

    /**
     * Cofa zlecone usunięcie. Sklep wraca do stanu sprzed kliknięcia, bo w
     * karencji nie ruszamy niczego poza widocznością.
     */
    public function cancel(Shop $shop): void
    {
        if ($shop->deletion_scheduled_at === null) {
            return;
        }

        $shop->forceFill(['deletion_scheduled_at' => null])->save();

        $this->mailCancelled($shop);
    }

    /**
     * Usuwa sklep, konto właściciela i wszystko, co do nich należy. Operacja
     * nieodwracalna.
     */
    public function erase(Shop $shop): void
    {
        $owner = $shop->owner;
        // Konta pracowników zbieramy PRZED transakcją: `shop_employees` znika
        // razem ze sklepem (kaskada FK), więc po usunięciu nie ma już czym
        // ustalić, kto tu pracował.
        $employees = $this->employeesToErase($shop);
        $directories = $this->directories($shop, $owner);

        foreach ($employees as $employee) {
            $directories[] = 'users/'.$employee->getKey();   // awatar
        }
        $slug = $shop->slug;
        $name = $shop->name;

        DB::transaction(function () use ($shop, $owner, $employees): void {
            // Pożegnanie z `shop_id = null` — z identyfikatorem sklepu wpadłoby
            // w czystkę `email_messages` kilka linii niżej i nigdy by nie wyszło.
            $this->mailErased($shop, $owner);

            ReservedSlug::updateOrCreate(
                ['slug' => $shop->slug],
                ['released_at' => Carbon::now()->addDays((int) config('shop.deletion.slug_quarantine_days'))],
            );

            EmailMessage::where('shop_id', $shop->id)->delete();

            if ($owner === null) {
                // Sklep-sierota (właściciel usunięty wcześniej) — kasujemy sam sklep.
                $shop->delete();

                return;
            }

            DB::table('sessions')->where('user_id', $owner->id)->delete();
            DB::table('password_reset_tokens')->where('email', $owner->email)->delete();

            // Konta pracowników kasujemy TAK SAMO jak konto właściciela. Kaskada
            // FK zdejmuje tylko członkostwo, a samo konto zostawało: imię,
            // nazwisko i adres e-mail obcej osoby, trzymane po sklepie, którego
            // już nie ma — razem z działającym logowaniem. Sprzedawcy mówimy, że
            // usuwamy wszystko, więc to „wszystko" musi obejmować także ludzi,
            // których on tu wpuścił.
            foreach ($employees as $employee) {
                DB::table('sessions')->where('user_id', $employee->getKey())->delete();
                DB::table('password_reset_tokens')->where('email', $employee->email)->delete();
                $employee->delete();
            }

            $owner->delete();
        });

        // Dopiero po commicie: pliku nie da się cofnąć razem z transakcją.
        foreach ($directories as $directory) {
            Storage::disk('public')->deleteDirectory($directory);
        }

        Log::info('Sklep usunięty.', ['slug' => $slug, 'name' => $name]);
    }

    /**
     * Katalogi na dysku publicznym należące do sklepu i jego właściciela.
     * Produkty bierzemy z `withTrashed()`, bo miękko usunięty produkt wciąż ma
     * swoje pliki na dysku.
     *
     * @return list<string>
     */
    private function directories(Shop $shop, ?User $owner): array
    {
        $directories = Product::withTrashed()
            ->where('shop_id', $shop->id)
            ->pluck('id')
            ->map(fn (int $id): string => 'products/'.$id)
            ->all();

        $directories[] = 'shops/'.$shop->id;   // logo i grafiki sklepu
        $directories[] = 'og/'.$shop->id;      // karta na Facebooka

        if ($owner !== null) {
            $directories[] = 'users/'.$owner->id;   // awatar
        }

        return $directories;
    }

    /**
     * Konta pracowników, które znikają razem z tym sklepem.
     *
     * WYŁĄCZNIE ci, dla których to jedyne miejsce pracy. Osoba zatrudniona
     * także gdzie indziej (tabela członkostw na to pozwala — księgowa, wirtualna
     * asystentka) traci tylko to jedno członkostwo; skasowanie jej konta
     * odcięłoby ją od cudzego sklepu, który z tą decyzją nie ma nic wspólnego.
     *
     * @return Collection<int, User>
     */
    private function employeesToErase(Shop $shop): Collection
    {
        return User::query()
            ->whereHas('employments', fn ($query) => $query->where('shop_id', $shop->getKey()))
            ->whereDoesntHave('employments', fn ($query) => $query->where('shop_id', '!=', $shop->getKey()))
            ->get();
    }

    /**
     * Potwierdzenie zlecenia. Mówi DATĘ i prowadzi wprost do miejsca, w którym
     * da się to cofnąć — mail o nieodwracalnej operacji bez drogi odwrotu byłby
     * okrucieństwem, a to jedyna wiadomość, którą sprzedawca dostanie, jeśli
     * kliknął przez pomyłkę.
     */
    private function mailScheduled(Shop $shop): void
    {
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $date = $shop->deletion_scheduled_at->format('d.m.Y');

        EmailMessage::create([
            'priority' => MailPriority::High,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Usunięcie sklepu '.$shop->name.' zaplanowane na '.$date.' — Kramio',
            'preheader' => 'Masz czas do '.$date.', żeby to cofnąć.',
            'heading' => 'Usuniemy Twój sklep '.$date,
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Przyjęliśmy zlecenie usunięcia sklepu **'.$shop->name.'**. Sklep jest już niewidoczny dla klientów, '
                        .'a **'.$date.'** usuniemy go razem z Twoim kontem — trwale, bez możliwości odzyskania.',
                ],
                [
                    'Jeśli to pomyłka albo zmienisz zdanie, zatrzymaj usunięcie w panelu. Do tego dnia wszystko wraca '
                        .'jednym kliknięciem: produkty, zamówienia i klienci czekają nietknięci.',
                ],
            ],
            'action_text' => 'Zatrzymaj usunięcie',
            'action_url' => route('seller.deletion.show'),
            'outro_lines' => [
                'Masz pytania? Po prostu odpowiedz na tę wiadomość.',
            ],
        ]);
    }

    /**
     * Potwierdzenie cofnięcia — bez niego sprzedawca nie ma pewności, że
     * kliknięcie zadziałało, a stawka jest za wysoka na domysły. Wychodzi też
     * wtedy, gdy usunięcie zatrzymał admin: właściciel musi o tym wiedzieć.
     */
    private function mailCancelled(Shop $shop): void
    {
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        EmailMessage::create([
            'priority' => MailPriority::High,
            'shop_id' => $shop->id,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Usunięcie sklepu '.$shop->name.' zostało zatrzymane — Kramio',
            'preheader' => 'Sklep działa dalej, nic nie przepadło.',
            'heading' => 'Twój sklep zostaje',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Zatrzymaliśmy usunięcie sklepu **'.$shop->name.'**. Sklep znów jest widoczny dla klientów, '
                        .'a wszystkie dane są na swoim miejscu.',
                ],
            ],
            'action_text' => 'Wróć do panelu',
            'action_url' => route('seller.dashboard'),
            'outro_lines' => [
                'Jeśli to nie Ty zlecałeś usunięcie, odpowiedz na tę wiadomość — sprawdzimy, co się stało.',
            ],
        ]);
    }

    /**
     * Pożegnanie. Wołane WEWNĄTRZ transakcji, z `shop_id = null`: wiersz musi
     * przeżyć czystkę `email_messages` tego sklepu, a przy rollbacku zniknąć
     * razem z resztą.
     */
    private function mailErased(Shop $shop, ?User $owner): void
    {
        if ($owner === null) {
            return;
        }

        EmailMessage::create([
            'priority' => MailPriority::High,
            'shop_id' => null,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Sklep '.$shop->name.' został usunięty — Kramio',
            'preheader' => 'Konto i dane sklepu zostały trwale usunięte.',
            'heading' => 'Sklep '.$shop->name.' został usunięty',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Usunęliśmy sklep **'.$shop->name.'** razem z Twoim kontem w Kramio. Produkty, zamówienia, '
                        .'klienci i ustawienia zostały trwale skasowane — nie mamy już ich kopii.',
                ],
                [
                    'Dokumenty wystawione przez Twoje konto w systemie fakturowym zostają tam, gdzie były — to osobny '
                        .'system i usunięcie sklepu w Kramio ich nie dotyczy.',
                ],
                [
                    'Gdybyś kiedyś chciał wrócić, wystarczy założyć konto od nowa. Dziękujemy, że byłeś z nami.',
                ],
            ],
            'outro_lines' => [
                'To ostatnia wiadomość, jaką od nas dostajesz.',
            ],
        ]);
    }
}
