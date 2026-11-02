<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\Shop;
use App\Models\SubscriptionNotice;
use App\Support\Money;
use App\Support\PackageFeatures;
use App\Support\Vocative;
use Illuminate\Support\Facades\Log;

/**
 * Cykl życia płatnego abonamentu: przypomnienia przed terminem, karencja i
 * moment wyłączenia funkcji.
 *
 * Wołane raz na dobę z `subscriptions:check`. Cała klasa jest IDEMPOTENTNA —
 * powtórzony bieg (drugi cron, ręczne odpalenie, awaria w połowie) nie wyśle
 * drugiego maila ani nie schowa produktów po raz drugi. Ślad trzyma
 * `subscription_notices`, kluczowany terminem, więc po odnowieniu przypomnienia
 * ruszają od nowa same.
 *
 * PRZYPOMNIENIA MAJĄ JEDNĄ TREŚĆ dla wszystkich progów (decyzja Rafała): mail
 * mówi DATĘ, nie „za ile dni". Progi da się wtedy zmienić w configu bez pisania
 * nowych tekstów, a sprzedawca dostaje trzy spójne wiadomości, nie trzy różne.
 */
class SubscriptionLifecycle
{
    public function __construct(private ProductLimitLock $lock) {}

    /**
     * Przegląd wszystkich sklepów z terminem: przypomnienia i zamek.
     *
     * @return array{reminders: int, locked: int}
     */
    public function run(): array
    {
        $reminders = 0;
        $locked = 0;

        Shop::query()
            ->whereNotNull('subscription_ends_at')
            ->where('comped', false)
            ->with('owner')
            ->chunkById(100, function ($shops) use (&$reminders, &$locked): void {
                foreach ($shops as $shop) {
                    // Pakiet darmowy nie ma czego przypominać ani gasić — datę
                    // mógł zostawić po sobie wcześniejszy, płatny abonament.
                    if ($shop->subscriptionLocksAt() === null) {
                        continue;
                    }

                    $reminders += $this->remind($shop) ? 1 : 0;
                    $locked += $this->lockIfExpired($shop) ? 1 : 0;
                }
            });

        if ($reminders > 0 || $locked > 0) {
            Log::info('Cykl abonamentów: powiadomienia wysłane.', [
                'reminders' => $reminders,
                'locked' => $locked,
            ]);
        }

        return ['reminders' => $reminders, 'locked' => $locked];
    }

    /**
     * Przypomnienie, jeśli DZIŚ wypada któryś z progów. Zwraca, czy wysłano.
     *
     * Warunek jest „nie więcej niż X dni", nie „dokładnie X": gdyby cron nie
     * odpalił jednego dnia (awaria, wyłączony scheduler), warunek na równość
     * przepuściłby próg bez słowa. Wpis w `subscription_notices` pilnuje, żeby
     * doganianie nie wysłało trzech maili w jednym dniu — po najwyższym progu
     * zostaje tylko najbliższy nadchodzący.
     */
    private function remind(Shop $shop): bool
    {
        $endsAt = $shop->subscription_ends_at;

        if ($endsAt->isPast()) {
            return false;
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($endsAt->copy()->startOfDay(), false);
        $thresholds = collect(config('shop.subscription.reminder_days'))
            ->sort()
            ->values();

        // Najmniejszy próg, który już wypadł — czyli ten najbardziej naglący.
        $due = $thresholds->first(fn (int $days): bool => $daysLeft <= $days);

        if ($due === null) {
            return false;
        }

        return $this->once($shop, SubscriptionNotice::reminderKind($due), function () use ($shop): void {
            $this->mailReminder($shop);
        });
    }

    /**
     * Zamek po karencji: schowanie nadwyżki produktów i mail o tym, co się
     * stało. Zwraca, czy zamek właśnie zadziałał.
     *
     * Mail i zamek są w jednym wpisie: sprzedawca ma dowiedzieć się o zmianie
     * dokładnie wtedy, gdy zmiana nastąpiła, a nie osobnym torem.
     */
    private function lockIfExpired(Shop $shop): bool
    {
        if ($shop->subscriptionActive()) {
            return false;
        }

        return $this->once($shop, SubscriptionNotice::KIND_LOCKED, function () use ($shop): void {
            $hidden = $this->lock->enforce($shop);
            $this->mailLocked($shop, $hidden);
        });
    }

    /**
     * Wykonuje akcję najwyżej raz dla danego sklepu, rodzaju i terminu.
     * Zwraca, czy akcja poszła teraz.
     *
     * Wpis powstaje PRZED akcją — gdyby wysyłka maila wybuchła, wolimy zgubić
     * jedno powiadomienie niż zapętlić codzienne ponawianie.
     */
    private function once(Shop $shop, string $kind, callable $action): bool
    {
        $exists = SubscriptionNotice::where('shop_id', $shop->id)
            ->where('kind', $kind)
            ->where('ends_at', $shop->subscription_ends_at)
            ->exists();

        if ($exists) {
            return false;
        }

        SubscriptionNotice::create([
            'shop_id' => $shop->id,
            'kind' => $kind,
            'ends_at' => $shop->subscription_ends_at,
        ]);

        $action();

        return true;
    }

    /**
     * Przypomnienie o terminie. Mówi datę i kwotę, bo to jedyne dwie rzeczy
     * potrzebne do przelewu; karencję wymienia wprost, żeby nikt nie panikował,
     * że spóźniony o dzień przelew zgasi mu sklep.
     */
    private function mailReminder(Shop $shop): void
    {
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $grace = (int) config('shop.subscription.grace_days');

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Abonament pakietu '.$shop->packageName().' kończy się '.$shop->subscription_ends_at->format('d.m.Y').' — Kramio',
            'preheader' => 'Przedłuż, żeby sklep działał bez przerwy.',
            'heading' => 'Termin Twojego pakietu się zbliża',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Pakiet **'.$shop->packageName().'** dla sklepu **'.$shop->name.'** jest opłacony do **'
                        .$shop->subscription_ends_at->format('d.m.Y').'**.',
                    'Przedłużenie na kolejny rok kosztuje **'.Money::pln($shop->priceYearly()).'**.',
                ],
                [
                    $grace > 0
                        ? 'Po tej dacie masz jeszcze '.$grace.' '.trans_choice('{1}dzień|[2,4]dni|[5,*]dni', $grace).' na opłatę — sklep działa wtedy normalnie. Dopiero potem funkcje płatnego pakietu się wyłączą.'
                        : 'Po tej dacie funkcje płatnego pakietu się wyłączą.',
                    'Sklep i zamówienia zostają w każdym wypadku — wyłączeniu podlegają tylko dodatki z pakietu.',
                ],
            ],
            'action_text' => 'Przedłuż pakiet',
            'action_url' => route('seller.package.show'),
            'outro_lines' => [
                'Masz pytania? Po prostu odpowiedz na tę wiadomość.',
            ],
        ]);
    }

    /**
     * Mail w chwili wyłączenia — mówi WPROST, co się właśnie zmieniło: na jakich
     * zasadach działa sklep, ile produktów schowaliśmy i że wszystko wraca po
     * opłacie. Bez tego sprzedawca zobaczyłby zmiany w panelu i nie wiedział,
     * czy to awaria.
     */
    private function mailLocked(Shop $shop, int $hidden): void
    {
        $owner = $shop->owner;

        if ($owner === null) {
            return;
        }

        $freeName = config('shop.packages.'.config('shop.default_package').'.name');

        $second = [
            'Sklep, zamówienia i wszystkie dotychczasowe dane działają dalej — wyłączone są funkcje płatnego pakietu.',
        ];

        if ($hidden > 0) {
            $second[] = 'Limit produktów w pakiecie '.$freeName.' to '.(int) $shop->entitlement('max_products')
                .', więc '.$hidden.' '.trans_choice('{1}produkt został ukryty|[2,4]produkty zostały ukryte|[5,*]produktów zostało ukrytych', $hidden)
                .' w sklepie. Nic nie zostało usunięte — możesz też sam wybrać, które produkty mają być widoczne.';
        }

        // Banery nad limitem nie mają „ukrycia" w bazie — storefront po prostu
        // pokazuje pierwsze z listy (Shop::storefrontBanners). Liczymy więc
        // na żywo, ile wypadło, żeby mail mówił to samo co sklep.
        $banners = $shop->banners()->count();
        $bannerLimit = max(0, (int) $shop->entitlement('max_banners'));
        if ($banners > $bannerLimit) {
            $second[] = 'Na stronie głównej widać teraz '.$bannerLimit.' z '.$banners.' banerów — kolejne z listy wrócą po opłaceniu pakietu.';
        }

        $second[] = 'Po opłaceniu pakietu wszystko wraca takie, jak było, razem z ukrytymi produktami i ustawieniami.';

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'to_email' => $owner->email,
            'to_name' => trim($owner->name.' '.$owner->surname),
            'subject' => 'Pakiet '.$shop->packageName().' wygasł — sklep działa na zasadach pakietu '.$freeName,
            'preheader' => 'Co się zmieniło i jak to odwrócić.',
            'heading' => 'Twój pakiet wygasł',
            'greeting' => Vocative::greeting($owner->name),
            'intro_lines' => [
                [
                    'Abonament pakietu **'.$shop->packageName().'** dla sklepu **'.$shop->name.'** skończył się '
                        .$shop->subscription_ends_at->format('d.m.Y').', a czas na opłatę minął.',
                    'Od teraz sklep działa na zasadach pakietu **'.$freeName.'**.',
                ],
                $second,
                array_merge(
                    ['**Co dostajesz z powrotem po opłacie:**'],
                    array_map(
                        fn (string $feature): string => '• '.$feature,
                        PackageFeatures::forShop($shop, raw: true),
                    ),
                ),
            ],
            'action_text' => 'Opłać pakiet',
            'action_url' => route('seller.package.show'),
            'outro_lines' => [
                'Masz pytania? Po prostu odpowiedz na tę wiadomość.',
            ],
        ]);
    }
}
