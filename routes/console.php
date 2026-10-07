<?php

use App\Support\Mode;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Outbox maili: krótki proces co minutę (bezpieczne na shared-hoście, nie demon).
// Wymaga wpisu crona na serwerze: * * * * * php artisan schedule:run
//
// `appendOutputTo`, bo scheduler domyślnie wyrzuca wyjście komendy do /dev/null.
// Ta komenda padła pięć razy (25.08, 31.08, 05.09, 09.09, 11.09.2026) z kodem
// wyjścia 254 i za każdym razem w logu został SAM NUMER, bez zdania o powodzie —
// a 254 to zwykle proces ubity albo błąd krytyczny PHP, czyli dokładnie to, czego
// nie widać z zewnątrz. Laravel dokleja `>> plik 2>&1`, więc łapiemy też stderr.
//
// Plik nie urośnie bez opamiętania: przy pustym outboksie komenda kończy się bez
// jednego znaku na wyjściu, więc rosną tylko minuty z realną robotą (jedna linia)
// i awarie. Nazwa spoza wzorca `laravel-*.log` celowo — ekran „Błędy w logach"
// czyta tylko dzienniki aplikacji i ten plik go nie dotyczy.
Schedule::command('email:dispatch')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/email-dispatch.log'));

// Kolejka zadań w tle (na razie: wystawianie faktur VAT). Świadomie NIE demon
// `queue:work`, lecz krótki bieg, który KOŃCZY się, gdy kolejka pusta — na idle
// wychodzi natychmiast, obciążając LVE tylko realną robotą. `--max-time` domyka
// proces przed kolejną minutą; `withoutOverlapping` nie pozwala się nakładać.
Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping();

// Przesyłki InPost: dopytanie o stan świeżo nadanych paczek (zakup u InPostu
// jest asynchroniczny — numer do śledzenia i etykieta pojawiają się chwilę po
// nadaniu). Co minutę, bo sprzedawca stoi nad panelem i czeka na etykietę;
// zapytanie leci tylko dla przesyłek jeszcze nieopłaconych, więc zwykle zero.
Schedule::command('shipments:refresh')->everyMinute()->withoutOverlapping();

// Doręczenia: raz na godzinę pytamy o paczki w drodze, żeby zapisać DATĘ ODBIORU
// (dla paczkomatu = moment wyjęcia paczki przez klienta). Od niej liczy się
// ustawowe 14 dni na odstąpienie. Rzadko, bo nikt nie czeka nad ekranem,
// a paczka potrafi leżeć w skrytce kilka dni.
Schedule::command('shipments:refresh --deliveries')->hourly()->withoutOverlapping();

// Abonamenty: przypomnienia przed terminem i zamek po karencji. Raz na dobę o
// świcie — maile idą przez outbox, więc godzina jest tylko chwilą, w której
// wpadają do kolejki. Komenda jest idempotentna, więc powtórka nic nie psuje.
//
// Sklep dedykowany nie ma abonamentu (`comped`, pakiet za 0 zł), więc komenda
// nie miałaby czego znaleźć. Nie rejestrujemy jej mimo to: harmonogram ma
// mówić prawdę o tym, co ta instalacja robi, a nie liczyć na to, że komenda
// sama nic nie zrobi.
if (Mode::saas()) {
    Schedule::command('subscriptions:check')->dailyAt('06:10')->withoutOverlapping();
}

// Kopia zapasowa: baza + zdjęcia + .env do katalogu poza domeną. Nocą, z dala
// od komend abonamentowych; przebieg trwa sekundy, więc limit procesów konta
// nie jest zagrożony. Wpis rejestrujemy tylko przy włączonych kopiach — dzięki
// temu `BACKUP_ENABLED=false` naprawdę wycisza harmonogram, zamiast odpalać co
// noc komendę, która i tak od razu wychodzi.
if (config('backup.enabled')) {
    // Kopia leci DWA RAZY na dobę (04:00 i 16:00) — każda godzina to osobny wpis
    // w harmonogramie, bo Laravel nie zna „co 12 godzin od tej godziny".
    // `withoutOverlapping()` chroni pojedynczy przebieg przed samym sobą; między
    // wpisami mutexy są różne, ale 12 godzin odstępu czyni to teoretycznym.
    foreach ((array) config('backup.daily_at') as $time) {
        Schedule::command('backup:run')
            ->dailyAt($time)
            ->withoutOverlapping();
    }

    // Strażnik: pilnuje ŚLADU po kopii, nie samego przebiegu — awaria, przez
    // którą `backup:run` w ogóle się nie uruchamia, nie zgłosi się sama.
    // O 9:00, bo alarm ma trafić na Discorda w godzinach, w których ktoś patrzy.
    Schedule::command('backup:check')->dailyAt('09:00')->withoutOverlapping();
}

// Usuwanie sklepów: kasuje te po karencji i zwalnia adresy po kwarantannie.
// Tuż po abonamentach, bo obie komendy są dobowe i nie mają na siebie wpływu.
//
// W sklepie dedykowanym NIE REJESTRUJEMY jej wcale, i to jest ważniejsze niż
// przy `subscriptions:check`. To jedyna komenda w całym harmonogramie, która
// kasuje sklep razem z historią sprzedaży. Ekran zlecający usunięcie jest w
// tym trybie zamknięty (routes/web.php), więc nie ma jej co uruchamiać — ale
// gdyby kiedykolwiek jakiś sklep miał ustawione `deletion_scheduled_at`,
// nieobecność tej komendy jest ostatnią barierą między pomyłką a bezpowrotną
// utratą danych klienta.
if (Mode::saas()) {
    Schedule::command('shops:purge')->dailyAt('06:20')->withoutOverlapping();
}

// Punkty za zakupy: wygaszanie przeterminowanych porcji. Rejestrowane w obu
// trybach — punkty to funkcja sklepu, nie abonamentu. Gdy żaden sklep ich nie
// używa, przebieg to jedno puste zapytanie.
Schedule::command('loyalty:expire')->dailyAt('06:30')->withoutOverlapping();

// Przypomnienie „punkty wkrótce wygasną" — o 10:00, bo o tej porze ludzie
// czytają pocztę, a mail ma skłonić do działania. Maile idą przez outbox.
Schedule::command('loyalty:remind')->dailyAt('10:00')->withoutOverlapping();
