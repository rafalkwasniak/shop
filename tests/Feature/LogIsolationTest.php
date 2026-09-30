<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Suita nie ma prawa dopisać ani jednej linii do dzienników produkcji.
 *
 * Bliźniak `StorageIsolationTest` — ta sama zasada, inny rodzaj pliku. Testy
 * chodzą w katalogu produkcyjnym (shared hosting, jedna kopia kodu), więc
 * `storage/logs` to te same pliki, do których zagląda się przy awarii.
 *
 * Dwie szkody, obie realne:
 *   1. Ekran „Błędy w logach" w Ustawieniach admina liczy poziomy ERROR
 *      w `laravel-<data>.log`. Wpisy z suity kazały mu zgłaszać awarie w dniach,
 *      w których nic się nie stało (6 sztuk 28.09.2026) — a ekran diagnostyczny,
 *      który kłamie, przestaje być czytany.
 *   2. Kanały `paynow`, `shipx` i `fakturownia` to ŚLAD AUDYTOWY PIENIĘDZY,
 *      trzymany 365 dni. Wymyślone kwoty z fabryk w jednym pliku z realnymi
 *      płatnościami sprzedawców psują dowód, po który sięga się dokładnie
 *      wtedy, gdy trzeba odtworzyć, co się stało z czyimiś pieniędzmi.
 *
 * Ten test pilnuje gardy `Tests\TestCase::isolateLogChannels()`.
 */
class LogIsolationTest extends TestCase
{
    /**
     * Każdy kanał piszący do pliku, po nazwie z `config/logging.php`. Nowy kanał
     * dzienny dochodzi do tej listy razem z konfiguracją — a gdyby ktoś zapomniał,
     * łapie go `test_no_file_writing_channel_is_left_unguarded()` niżej.
     */
    public static function channelProvider(): array
    {
        return [
            'daily (dziennik aplikacji)' => ['daily'],
            'single' => ['single'],
            'slow_queries (wolne zapytania)' => ['slow_queries'],
            'paynow (audyt płatności)' => ['paynow'],
            'shipx (audyt nadań InPost)' => ['shipx'],
            'fakturownia (audyt faktur)' => ['fakturownia'],
        ];
    }

    #[DataProvider('channelProvider')]
    public function test_channel_writes_do_not_reach_storage_logs(string $channel): void
    {
        $before = self::logDirectorySnapshot();

        Log::channel($channel)->error('KANAREK: ten wpis nie ma prawa wylądować na dysku.');

        $this->assertSame(
            $before,
            self::logDirectorySnapshot(),
            "Kanał [{$channel}] zapisał coś w storage/logs. Garda isolateLogChannels() go nie objęła."
        );
    }

    /**
     * Kanał domyślny, czyli zwykłe `Log::error()` rozsiane po kodzie aplikacji.
     * Osobno od listy wyżej, bo chroni go inny mechanizm — `LOG_CHANNEL=null`
     * w phpunit.xml — i chcemy wiedzieć, gdyby tamten wpis zniknął.
     */
    public function test_default_channel_writes_do_not_reach_storage_logs(): void
    {
        $before = self::logDirectorySnapshot();

        Log::error('KANAREK: kanał domyślny też nie pisze na dysk.');

        $this->assertSame($before, self::logDirectorySnapshot(), 'Kanał domyślny zapisał coś w storage/logs.');
    }

    /**
     * Garda obejmuje KAŻDY kanał z kluczem `path`, nie wyliczoną listę — bo nowy
     * kanał dzienny dołoży ktoś kiedyś bez czytania komentarza w `TestCase`.
     * Ten test sprawdza samą zasadę, nie konkretne nazwy.
     */
    public function test_no_file_writing_channel_is_left_unguarded(): void
    {
        $withPath = array_keys(array_filter(
            config('logging.channels', []),
            static fn (array $channel): bool => isset($channel['path']),
        ));

        $this->assertSame(
            [],
            $withPath,
            'Kanały ['.implode(', ', $withPath).'] mają w testach ścieżkę do pliku — '
            .'isolateLogChannels() powinien był podmienić je na NullHandler.'
        );
    }

    /**
     * Stan katalogu logów: nazwa pliku → rozmiar. Porównujemy rozmiary, a nie
     * samą listę nazw, bo dopisanie linii do ISTNIEJĄCEGO dziennika jest równie
     * szkodliwe jak utworzenie nowego — i to właśnie ten wariant zdarzał się
     * naprawdę (suita trafiała w plik z bieżącą datą, który już był).
     *
     * @return array<string, int>
     */
    private static function logDirectorySnapshot(): array
    {
        clearstatcache();

        $snapshot = [];

        foreach (glob(storage_path('logs/*')) ?: [] as $path) {
            $snapshot[basename($path)] = (int) filesize($path);
        }

        ksort($snapshot);

        return $snapshot;
    }
}
