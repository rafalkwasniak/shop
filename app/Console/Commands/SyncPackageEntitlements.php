<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;

/**
 * Dosypuje sklepom to, co ich pakiet daje DZIŚ, a czego nie było w dniu zakupu.
 *
 * Powód istnienia: uprawnienia są LEPKIE — `assignPackage()` robi snapshot na
 * sklepie, a `entitlement()` czyta ten snapshot, nie config. To celowe i dobre:
 * dzięki temu ręczne nadania (moduł dany komuś gestem) przeżywają odnowienie
 * abonamentu, a zmiana cennika nie zabiera nikomu tego, za co zapłacił.
 *
 * Ta sama własność ma jednak drugą stronę: HOJNOŚĆ TEŻ NIE DOCHODZI. Podniesienie
 * limitu w `config/shop.php` nie zmienia niczego sklepom, które już istnieją —
 * dostałyby nowy limit dopiero przy najbliższej zmianie pakietu. Cennik
 * obiecywałby wtedy 60 produktów komuś, kto w panelu widzi „3 / 24", i miałby
 * rację obie strony naraz.
 *
 * ZASADA: WYŁĄCZNIE W GÓRĘ. Wartość ze snapshotu ustępuje tylko wtedy, gdy ta z
 * configu jest wyższa (liczby) albo gdy config daje `true` tam, gdzie snapshot
 * ma `false`. Odwrotnie — nigdy: obniżka limitu w cenniku nie może po cichu
 * zabrać nikomu produktów z witryny, a wyłączenie funkcji nie może skasować
 * ręcznego nadania. Zejście z pakietu ma własną, świadomą ścieżkę (zakup
 * niższego pakietu), która ukrywa nadwyżkę i tłumaczy to mailem.
 *
 * Komendy NIE MA w harmonogramie — uruchamia się ją ręcznie po zmianie cennika.
 * Cykliczna kasowałaby ręczne nadania w drugą stronę przy pierwszej korekcie
 * configu w dół.
 */
class SyncPackageEntitlements extends Command
{
    protected $signature = 'packages:sync-entitlements {--apply : Zapisz zmiany (bez tego tylko podgląd)}';

    protected $description = 'Dosypuje istniejącym sklepom uprawnienia, które ich pakiet daje dziś — wyłącznie w górę';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $changed = 0;

        foreach (Shop::query()->get() as $shop) {
            $current = $shop->entitlements ?? [];
            $target = config("shop.packages.{$shop->package}.entitlements");

            if (! is_array($target)) {
                $this->warn("{$shop->name}: pakiet {$shop->package} nie istnieje w cenniku — pomijam.");

                continue;
            }

            $updates = [];

            foreach ($target as $key => $value) {
                if (! $this->isUpgrade($current[$key] ?? null, $value)) {
                    continue;
                }

                $updates[$key] = $value;
            }

            if ($updates === []) {
                continue;
            }

            $changed++;
            $this->line(sprintf(
                '%-28s %-10s %s',
                mb_substr($shop->name, 0, 28),
                $shop->package,
                collect($updates)->map(fn ($v, $k) => $k.': '.$this->show($current[$k] ?? null).' → '.$this->show($v))->implode(', '),
            ));

            if ($apply) {
                $shop->forceFill(['entitlements' => array_merge($current, $updates)])->save();
            }
        }

        if ($changed === 0) {
            $this->info('Wszystkie sklepy mają już to, co daje ich pakiet.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info($apply
            ? "Zaktualizowano sklepów: {$changed}."
            : "Do zaktualizowania: {$changed}. Uruchom z --apply, żeby zapisać.");

        return self::SUCCESS;
    }

    /**
     * Czy wartość z configu jest HOJNIEJSZA od tej ze snapshotu.
     *
     * Brak klucza w snapshocie to też podwyżka: uprawnienie dodane po zakupie
     * (jak `max_employees`) ma dojść, zamiast czekać na zmianę pakietu.
     */
    private function isUpgrade(mixed $snapshot, mixed $config): bool
    {
        if ($snapshot === null) {
            return true;
        }

        if (is_int($config) && is_int($snapshot)) {
            return $config > $snapshot;
        }

        if (is_bool($config) && is_bool($snapshot)) {
            return $config === true && $snapshot === false;
        }

        // Typy się nie zgadzają — nie zgadujemy, zostawiamy człowiekowi.
        return false;
    }

    private function show(mixed $value): string
    {
        return match (true) {
            $value === null => 'brak',
            is_bool($value) => $value ? 'tak' : 'nie',
            default => (string) $value,
        };
    }
}
