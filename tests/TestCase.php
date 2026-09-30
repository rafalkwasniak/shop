<?php

namespace Tests;

use App\Jobs\GenerateShopOgImage;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\NullHandler;

abstract class TestCase extends BaseTestCase
{
    /**
     * Dyski, które w produkcji trzymają pliki użytkowników, wraz z katalogiem,
     * którego suicie NIE WOLNO zobaczyć. Patrz `isolateDisks()`.
     *
     * @var array<string, string>
     */
    private const PRODUCTION_DISKS = [
        'public' => 'app/public',    // awatary, zdjęcia produktów, loga, karty OG
        'local' => 'app/private',    // dysk domyślny (FILESYSTEM_DISK=local)
    ];

    /**
     * Cztery gardy chroniące świat zewnętrzny przed suitą testów.
     *
     * 1. SQLITE-ONLY: gdyby phpunit.xml się zepsuł albo środowisko podłożyło
     *    produkcyjne połączenie, suita pada zamiast tknąć produkcję.
     *    Patrz DB_SECURITY.md, Warstwa 3.
     *
     * 2. ŻADNYCH PRAWDZIWYCH ŻĄDAŃ HTTP. Kosztowna lekcja z 2026-07-30: test
     *    fake'ował tylko endpoint płatności Paynow, a webhook uruchamiał job
     *    faktury — kolejka `sync` wykonała go natychmiast i poszedł NA ŻYWO do
     *    Fakturowni, wystawiając ~30 realnych faktur. `Http::fake()` z tablicą
     *    wzorców przepuszcza wszystko, czego wzorzec nie obejmuje, i o tym łatwo
     *    zapomnieć.
     *
     *    `preventStrayRequests()` odwraca domyślne zachowanie: nieudawane
     *    żądanie rzuca wyjątek zamiast lecieć do internetu. Dotyczy WSZYSTKICH
     *    integracji — Fakturownia, Paynow, DeepSeek (płatne tokeny!), GUS,
     *    Discord. Test, który czegoś nie zafake'ował, teraz o tym krzyczy.
     *
     * 3. ŻADNYCH PRAWDZIWYCH PLIKÓW. Lekcja z 2026-08-04, patrz `isolateDisks()`.
     *
     * 4. ŻADNYCH WPISÓW W DZIENNIKACH PRODUKCJI. Patrz `isolateLogChannels()`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();

        if ($driver !== 'sqlite' || $database !== ':memory:') {
            $this->fail(
                "ABORT: tests resolved DB connection [{$driver}:{$database}] "
                .'instead of sqlite::memory:. Refusing to run to protect production.'
            );
        }

        Http::preventStrayRequests();

        $this->isolateDisks();
        $this->isolateLogChannels();
        $this->skipShopCardRendering();
    }

    /**
     * Żaden kanał logowania nie pisze w testach do pliku.
     *
     * `LOG_CHANNEL=null` w phpunit.xml ucisza tylko kanał DOMYŚLNY. Kanały
     * wołane po nazwie — `Log::channel('paynow')`, `'shipx'`, `'fakturownia'` —
     * omijają tę wartość i pisały dalej: jeden przebieg suity zostawiał prawie
     * 300 linii w dziennikach, które na produkcji są ŚLADEM AUDYTOWYM PIENIĘDZY
     * (stąd 365 dni retencji w config/logging.php, a nie 14). Wymyślone kwoty
     * z fabryk w tych samych plikach co realne płatności sprzedawców to nie
     * bałagan, tylko zepsuty dowód — a sięga się po niego dokładnie wtedy, gdy
     * trzeba odtworzyć, co się stało z czyimiś pieniędzmi.
     *
     * Ta sama zasada co przy dyskach: nie „autor testu pamiętał", tylko „plik
     * produkcji jest poza zasięgiem suity, kropka". Dlatego uciszamy KAŻDY kanał
     * z kluczem `path`, a nie wyliczoną trójkę — nowy kanał dzienny dołoży ktoś
     * kiedyś bez czytania tego komentarza.
     *
     * `Log::spy()` i `Log::shouldReceive()` w konkretnych testach działają jak
     * dawniej — podmieniają całą fasadę i konfiguracji nie dotykają.
     */
    private function isolateLogChannels(): void
    {
        foreach (config('logging.channels', []) as $name => $channel) {
            if (! isset($channel['path'])) {
                continue;
            }

            config()->set("logging.channels.{$name}", [
                'driver' => 'monolog',
                'handler' => NullHandler::class,
            ]);
        }
    }

    /**
     * Dyski w testach wskazują na katalog tymczasowy, NIGDY na pliki produkcji.
     *
     * Kosztowna lekcja z 2026-08-04: `ShopEraser` kasuje katalogi po ID
     * (`users/{id}`, `products/{id}`), a testy usuwania sklepu nie miały
     * `Storage::fake('public')`. Baza testowa to sqlite `:memory:`, więc ID
     * startują od 1 — pierwszy sprzedawca w teście dostał `id = 1` i suita
     * skasowała REALNY katalog `storage/app/public/users/1`, czyli awatar
     * administratora platformy. Zdjęcia produktów ocalały tylko dlatego, że
     * produkcyjne ID doszły już do 27 i testowe „jedynki" nie miały w co trafić.
     * Przy pierwszym kliencie z ID w zakresie testowym zniknęłyby jego zdjęcia,
     * których nikt by nie odtworzył.
     *
     * Guard odwraca odpowiedzialność: nie „autor testu pamiętał o fake'u", tylko
     * „dysk produkcyjny jest poza zasięgiem suity, kropka". `Storage::fake()`
     * przestawia korzeń dysku na `storage/framework/testing/disks/*` i czyści go
     * przed każdym testem — a asercja niżej pilnuje, że naprawdę się przestawił
     * (gdyby Laravel zmienił zachowanie albo ktoś podmienił konfigurację dysków,
     * suita ma paść, a nie kasować pliki).
     *
     * Test może nadal wołać `Storage::fake('public')` u siebie — to no-op na
     * już-udawanym dysku i nie ma potrzeby tego usuwać.
     *
     * Konfigurację dysku przekazujemy jawnie, bo samo `Storage::fake()` bierze
     * z oryginału WYŁĄCZNIE `throw` (patrz `Storage::buildDiskConfiguration()`)
     * — gubi m.in. `url`, przez co `Storage::url()` zwraca ścieżkę względną
     * zamiast absolutnego adresu z `APP_URL`. Podmieniamy KORZEŃ, nie
     * zachowanie: test ma sprawdzać produkcyjną semantykę dysku, tylko na innych
     * plikach. (Bez tego padał `MailBrandingTest` — logo w mailu przestawało być
     * absolutnym URL-em, a taki link nie działa w kliencie pocztowym.)
     */
    private function isolateDisks(): void
    {
        $sandbox = storage_path('framework/testing/disks');

        foreach (self::PRODUCTION_DISKS as $disk => $productionPath) {
            $config = config("filesystems.disks.{$disk}", []);
            unset($config['root']);

            Storage::fake($disk, $config);

            $root = Storage::disk($disk)->path('');

            if (! str_starts_with($root, $sandbox)) {
                $this->fail(
                    "ABORT: disk [{$disk}] resolved to [{$root}] instead of the testing sandbox "
                    ."[{$sandbox}]. Refusing to run — the suite must never touch "
                    ."production files in storage/{$productionPath}."
                );
            }
        }
    }

    /**
     * Grafika sklepu do social mediów NIE powstaje w testach, chyba że test
     * jawnie o nią prosi.
     *
     * `ShopObserver` zleca ją przy każdym utworzeniu sklepu, a kolejka w testach
     * jest synchroniczna — więc każdy `Shop::factory()` składałby kartę 1200×630
     * w Imagicku. Pojedynczo to ułamek sekundy i na produkcji nikogo nie boli
     * (raz na sklep), ale suita tworzy sklepy setki razy i czas rośnie z minut
     * do godzin.
     *
     * To NIE jest zamiatanie generatora pod dywan: `OgImageTest` wywołuje go
     * wprost i sprawdza wymiary, nazwę pliku oraz sprzątanie po starej wersji,
     * a zlecenie zadania ma własne testy na `Bus::assertDispatched`. Blokujemy
     * wyłącznie WYKONANIE zadania w tle, nie samo zlecenie.
     */
    private function skipShopCardRendering(): void
    {
        Bus::fake([GenerateShopOgImage::class]);
    }
}
