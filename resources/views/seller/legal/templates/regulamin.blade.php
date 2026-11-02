{{-- WZÓR REGULAMINU SKLEPU dla sprzedawcy — szablon deterministyczny.

     ZASADY, KTÓRYCH TU PILNUJEMY:
     - ZERO AI w runtime. To szablon z polami, nie generator. Wynik da się
       przejrzeć raz i mieć pewność, że u każdego sprzedawcy wyjdzie tak samo.
     - Opisujemy TYLKO to, co sklep faktycznie robi: metody dostawy i płatności
       bierzemy z ustawień, a nie wymieniamy „na wszelki wypadek". Regulamin
       obiecujący kuriera w sklepie bez kuriera jest gorszy niż jego brak.
     - ZERO PÓL „UZUPEŁNIJ" W WYNIKU. Wszystko, czego nie wiemy, zbiera kreator
       PRZED wstawieniem — bo szukanie oznaczonych miejsc w 12 tys. znaków to
       polowanie na igłę (zgłoszone przez Rafała po pierwszym kliknięciu).
     - Tożsamość i kontakt biorą się z `$dane` (odpowiedzi kreatora), a metody
       dostawy i płatności ze `$shop` — tych sprzedawca nie wpisuje ręcznie
       i mają opisywać to, co klient realnie zobaczy w kasie.
     - Bez platformy ODR — zamknięta 20.07.2025 (rozporządzenie 2024/3228).
       Skill podpowiada link do ODR; NIE ulegać, nasz własny regulamin też jej
       nie ma. Zamiast tego UOKiK i rzecznicy konsumentów.
     - AKAPITY W <div>, NIE W <p>: `HtmlSanitizer` (przez który przechodzi zapis
       podstrony) nie ma `p` na liście dozwolonych tagów i wyciąłby je, zostawiając
       zlepiony tekst. `div` to konwencja edytora Trix i zaślepki z config/pages.php.

     Zmienne: $shop (App\Models\Shop). --}}
@php
    // Tożsamość ma DWA warianty, bo §6 ust. 2 Regulaminu Kramio dopuszcza
    // działalność nierejestrowaną. Wymuszanie NIP-u odcięłoby grupę, do której
    // celujemy najmocniej — brak NIP-u to poprawna odpowiedź, nie luka.
    $sprzedawca = trim((string) ($dane['seller_name'] ?? ''));
    $nip = trim((string) ($dane['nip'] ?? ''));
    $zarejestrowana = $nip !== '';
    $adres = trim((string) ($dane['address'] ?? ''));
    $adresZwrotu = trim((string) ($dane['return_address'] ?? '')) ?: $adres;
    $wylaczenia = trim((string) ($dane['withdrawal_exclusions'] ?? ''));
    $dniWysylki = trim((string) ($dane['shipping_days'] ?? ''));

    // JEDNO ŹRÓDŁO PRAWDY: te same metody, które widzi klient w kasie.
    // NIE surowe przełączniki ani uprawnienia z pakietu — `onlinePaymentsEnabled()`
    // wymaga też skonfigurowanej integracji, a `pickupAvailable()` kompletnego
    // adresu. Regulamin obiecujący płatność online w sklepie bez wpiętego
    // operatora byłby wprost nieprawdziwy.
    $dostawa = collect($shop->availableDeliveryMethods())->map(fn ($m) => $m->value);
    $platnosci = collect($shop->availablePaymentMethods())->map(fn ($m) => $m->value);

    $koszt = fn (?string $cena) => $cena !== null && (float) $cena > 0
        ? \App\Support\Money::pln($cena)
        : 'bezpłatnie';

    // Fragmenty warunkowe wyliczamy TUTAJ, a nie inline w zdaniach. Dwa powody:
    // 1) `@if` przyklejone do cyfry (np. „§1@if") NIE kompiluje się — Blade
    //    wymaga w tym miejscu granicy wyrazu i zostawia dyrektywę jako tekst,
    //    co wywraca cały widok błędem składni;
    // 2) zdanie prawne czyta się lepiej, gdy w szablonie jest jednym ciągiem.
    $odFrazy = fn (?string $prog) => (float) $prog > 0
        ? ', a przy Zamówieniach od '.\App\Support\Money::pln($prog).' dostawa jest bezpłatna'
        : '';

    $telefon = filled($dane['phone'] ?? '') ? ', telefon '.$dane['phone'] : '';
@endphp
<h2>§1 / Kto prowadzi sklep</h2>
<div>Sklep internetowy „{{ $shop->name }}", działający pod adresem {{ $shop->host() }}, prowadzi
@if ($zarejestrowana)
    {{ $sprzedawca }}, NIP {{ $nip }}, z adresem: {{ $adres }}.
@else
    {{ $sprzedawca }}, prowadząc sprzedaż w ramach działalności nierejestrowanej w rozumieniu ustawy — Prawo przedsiębiorców, z adresem: {{ $adres }}.
@endif
</div>
<div>Kontakt: e-mail {{ $dane['email'] }}{{ $telefon }}{{ $shop->contactFormEnabled() ? ', a także formularz w zakładce „Kontakt”' : '' }}. Na wiadomości odpowiadamy w dni robocze.</div>
<div>Regulamin określa zasady zakupów w Sklepie i jest udostępniony nieodpłatnie w sposób umożliwiający jego pobranie, odtworzenie i utrwalenie.</div>

<h2>§2 / Definicje</h2>
<ul>
    <li><strong>Sprzedawca</strong> — podmiot wskazany w §1, prowadzący Sklep.</li>
    <li><strong>Sklep</strong> — sklep internetowy dostępny pod adresem {{ $shop->host() }}.</li>
    <li><strong>Klient</strong> — osoba składająca Zamówienie w Sklepie.</li>
    <li><strong>Konsument</strong> — osoba fizyczna dokonująca ze Sprzedawcą czynności prawnej niezwiązanej bezpośrednio z jej działalnością gospodarczą lub zawodową.</li>
    <li><strong>Przedsiębiorca na prawach konsumenta</strong> — osoba fizyczna zawierająca umowę bezpośrednio związaną z jej działalnością gospodarczą, gdy z treści umowy wynika, że nie ma ona dla niej charakteru zawodowego. Przysługują jej uprawnienia opisane w §8 (odstąpienie), §9 (wyjątki od odstąpienia) i §10 (reklamacje).</li>
    <li><strong>Towar</strong> — rzecz oferowana w Sklepie.</li>
    <li><strong>Zamówienie</strong> — oświadczenie Klienta zmierzające do zawarcia umowy sprzedaży Towaru.</li>
    <li><strong>Regulamin</strong> — niniejszy dokument.</li>
</ul>

<h2>§3 / Zasady korzystania ze Sklepu</h2>
<ol>
    <li>Do korzystania ze Sklepu potrzebne są: urządzenie z dostępem do Internetu i aktualną przeglądarką, aktywny adres e-mail oraz włączona obsługa niezbędnych plików cookies.</li>
    <li>Klient zobowiązuje się do podawania danych prawdziwych oraz do niedostarczania treści o charakterze bezprawnym.</li>
    <li>Ceny w Sklepie podane są w złotych polskich i zawierają podatek VAT. Cena nie obejmuje kosztów dostawy, które są wskazywane odrębnie przed złożeniem Zamówienia.</li>
</ol>

<h2>§4 / Konto Klienta</h2>
<ol>
    <li>Zakupy można zrobić bez zakładania konta. Założenie konta jest bezpłatne i dobrowolne.</li>
    <li>Konto dotyczy wyłącznie tego Sklepu i umożliwia podgląd historii Zamówień oraz zapisanych danych do wysyłki.</li>
    <li>Konto można w każdej chwili usunąć w jego ustawieniach. Usunięcie konta nie wpływa na Zamówienia już złożone ani na dokumenty księgowe, które Sprzedawca ma obowiązek przechowywać.</li>
</ol>

<h2>§5 / Składanie Zamówień i zawarcie umowy</h2>
<ol>
    <li>Informacje o Towarach w Sklepie nie stanowią oferty w rozumieniu Kodeksu cywilnego, lecz zaproszenie do zawarcia umowy.</li>
    <li>Zamówienie składa się, dodając Towar do koszyka, wskazując sposób dostawy i płatności oraz zatwierdzając Zamówienie przyciskiem oznaczonym jako zamówienie z obowiązkiem zapłaty.</li>
    <li>Umowa sprzedaży zostaje zawarta z chwilą potwierdzenia przyjęcia Zamówienia przez Sprzedawcę, przesłanego na adres e-mail Klienta. Potwierdzenie zawiera podsumowanie Zamówienia i niniejszy Regulamin.</li>
    <li>W przypadku gdy Sprzedawca nie może zrealizować Zamówienia w całości lub w części, informuje o tym Klienta i zwraca otrzymaną płatność w części niezrealizowanej.</li>
</ol>

<h2>§6 / Ceny i płatności</h2>
<ol>
    <li>Wiążąca jest cena widoczna przy Towarze w chwili złożenia Zamówienia.</li>
    <li>W przypadku obniżki ceny Sprzedawca podaje obok najniższą cenę Towaru z okresu 30 dni przed obniżką.</li>
    <li>Sklep udostępnia następujące sposoby płatności:
        <ul>
            @if ($platnosci->contains('online'))
                <li><strong>płatność online</strong> — BLIK, karta płatnicza lub szybki przelew, realizowana za pośrednictwem operatora płatności; Zamówienie kierowane jest do realizacji po otrzymaniu potwierdzenia płatności;</li>
            @endif
            @if ($platnosci->contains('bank_transfer'))
                <li><strong>przelew tradycyjny</strong> — na rachunek bankowy wskazany w podsumowaniu Zamówienia i w wiadomości potwierdzającej; Zamówienie kierowane jest do realizacji po zaksięgowaniu wpłaty;</li>
            @endif
            @if ($platnosci->contains('pay_on_pickup'))
                <li><strong>płatność przy odbiorze osobistym</strong> — gotówką lub w inny sposób uzgodniony ze Sprzedawcą w chwili odbioru;</li>
            @endif
            {{-- Pobranie nie jest pozycją w `availablePaymentMethods()`, bo Klient
                 go nie wybiera — wynika z dostawy. Regulamin musi je jednak
                 wymienić, inaczej Sklep sprzedający wyłącznie za pobraniem miałby
                 tu pustą listę. Świadomie NIE piszemy „gotówką": paczkomat
                 przyjmuje wyłącznie płatność bezgotówkową, więc obietnica gotówki
                 byłaby w regulaminie nieprawdziwa. --}}
            @if ($shop->cashOnDeliveryAvailable())
                <li><strong>płatność za pobraniem</strong> — przy odbiorze przesyłki, sposobami udostępnianymi przez przewoźnika w miejscu wydania; kwota pobrania obejmuje cenę Towarów i koszt dostawy;</li>
            @endif
            @if ($platnosci->isEmpty())
                {{-- Sklep bez metod płatności nie przyjmuje zamówień (Shop::acceptsOrders),
                     więc regulamin nie ma czego wyliczyć. Zamiast znacznika do uzupełnienia
                     — zdanie prawdziwe w każdej konfiguracji. Kreator ostrzega osobno. --}}
                <li>sposoby płatności dostępne w Sklepie prezentowane są przy składaniu Zamówienia;</li>
            @endif
        </ul>
    </li>
    <li>Do każdego Zamówienia Sprzedawca wystawia dowód sprzedaży. Fakturę wystawia na żądanie Klienta, zgłoszone najpóźniej przy składaniu Zamówienia.</li>
</ol>

<h2>§7 / Dostawa</h2>
<ol>
    <li>Sklep realizuje dostawę na terytorium Rzeczypospolitej Polskiej w następujący sposób:
        <ul>
            @if ($dostawa->contains('courier'))
                <li><strong>kurier</strong> — koszt {{ $koszt($shop->courier_cost) }}{{ $odFrazy($shop->courier_free_from) }};</li>
            @endif
            @if ($dostawa->contains('parcel_locker'))
                <li><strong>paczkomat</strong> — koszt {{ $koszt($shop->parcel_locker_cost) }}{{ $odFrazy($shop->parcel_locker_free_from) }};</li>
            @endif
            @if ($dostawa->contains('courier_cod'))
                <li><strong>kurier za pobraniem</strong> — koszt {{ $koszt($shop->courier_cod_cost) }}{{ $odFrazy($shop->courier_cod_free_from) }};</li>
            @endif
            @if ($dostawa->contains('parcel_locker_cod'))
                <li><strong>paczkomat za pobraniem</strong> — koszt {{ $koszt($shop->parcel_locker_cod_cost) }}{{ $odFrazy($shop->parcel_locker_cod_free_from) }};</li>
            @endif
            @if ($dostawa->contains('pickup'))
                <li><strong>odbiór osobisty</strong> — bezpłatnie, pod adresem wskazanym w §1, po wcześniejszym uzgodnieniu terminu;</li>
            @endif
            @if ($dostawa->isEmpty())
                <li>sposoby dostawy dostępne w Sklepie prezentowane są przy składaniu Zamówienia;</li>
            @endif
        </ul>
    </li>
    <li>Zamówienia realizowane są w terminie {{ $dniWysylki }} {{ trans_choice('dnia roboczego|dni roboczych|dni roboczych', (int) $dniWysylki) }} od zawarcia umowy, a przy płatności z góry — od zaksięgowania wpłaty.</li>
    <li>Ryzyko przypadkowej utraty lub uszkodzenia Towaru przechodzi na Klienta z chwilą wydania mu Towaru. W przypadku Konsumenta i Przedsiębiorcy na prawach konsumenta ryzyko przechodzi z chwilą wydania Towaru Konsumentowi, a nie przewoźnikowi.</li>
</ol>

<h2>§8 / Prawo odstąpienia od umowy</h2>
<ol>
    <li>Konsument oraz Przedsiębiorca na prawach konsumenta może odstąpić od umowy w terminie <strong>14 dni</strong> bez podania przyczyny.</li>
    <li>Termin liczy się od dnia, w którym Klient wszedł w posiadanie Towaru lub w którym osoba trzecia inna niż przewoźnik i wskazana przez Klienta weszła w jego posiadanie. W przypadku Zamówienia obejmującego wiele Towarów dostarczanych osobno — od objęcia w posiadanie ostatniego z nich.</li>
    <li>Aby odstąpić, wystarczy przesłać jednoznaczne oświadczenie na adres e-mail wskazany w §1. Można też skorzystać z <strong>formularza zwrotu dostępnego pod linkiem przesłanym w wiadomości o wysyłce Zamówienia</strong> — nie wymaga on zakładania konta. Skorzystanie z któregokolwiek sposobu jest wystarczające; Sprzedawca niezwłocznie potwierdza otrzymanie oświadczenia.</li>
    <li>Towar należy odesłać nie później niż w terminie 14 dni od odstąpienia, na adres: {{ $adresZwrotu }}. Bezpośrednie koszty odesłania Towaru ponosi Klient.</li>
    <li>Sprzedawca zwraca otrzymane płatności — wraz z kosztami dostawy w wysokości najtańszego zwykłego sposobu dostawy oferowanego w Sklepie — niezwłocznie, nie później niż w terminie 14 dni od otrzymania oświadczenia. Sprzedawca może wstrzymać się ze zwrotem do chwili otrzymania Towaru lub dowodu jego odesłania, zależnie od tego, co nastąpi wcześniej.</li>
    <li>Zwrot następuje tym samym sposobem płatności, którego użył Klient, chyba że Klient zgodzi się na inny sposób niewiążący się dla niego z kosztami.</li>
    <li>Klient odpowiada za zmniejszenie wartości Towaru wynikające z korzystania z niego w sposób wykraczający poza konieczny do stwierdzenia jego charakteru, cech i funkcjonowania.</li>
</ol>

<h2>§9 / Kiedy prawo odstąpienia nie przysługuje</h2>
<div>Prawo odstąpienia nie przysługuje w przypadkach wskazanych w ustawie o prawach konsumenta, w szczególności gdy przedmiotem świadczenia jest Towar:</div>
<ul>
    <li>nieprefabrykowany, wyprodukowany według specyfikacji Klienta lub służący zaspokojeniu jego zindywidualizowanych potrzeb;</li>
    <li>ulegający szybkiemu zepsuciu lub mający krótki termin przydatności do użycia;</li>
    <li>dostarczany w zapieczętowanym opakowaniu, którego po otwarciu nie można zwrócić ze względu na ochronę zdrowia lub ze względów higienicznych, jeżeli opakowanie zostało otwarte po dostarczeniu.</li>
</ul>
@if ($wylaczenia !== '')
    {{-- Ogólna lista wyżej zostaje ZAWSZE: wyłączenia z art. 38 działają
         niezależnie od tego, czy sprzedawca je wymienił. Tu dochodzi tylko
         doprecyzowanie, których Towarów w tym Sklepie dotyczą. --}}
    <div>W tym Sklepie powyższe wyłączenia dotyczą w szczególności: {{ $wylaczenia }}.</div>
@endif

<h2>§10 / Reklamacje</h2>
<ol>
    <li>Sprzedawca ma obowiązek dostarczyć Towar zgodny z umową i odpowiada wobec Konsumenta oraz Przedsiębiorcy na prawach konsumenta za brak tej zgodności na zasadach określonych w ustawie o prawach konsumenta.</li>
    <li>Reklamację można złożyć na adres e-mail lub adres pocztowy wskazany w §1. W zgłoszeniu warto podać numer Zamówienia, opis nieprawidłowości oraz żądanie (naprawa, wymiana, obniżenie ceny albo odstąpienie od umowy).</li>
    <li>Sprzedawca rozpatruje reklamację i informuje o jej wyniku w terminie <strong>14 dni kalendarzowych</strong> od jej otrzymania. Brak odpowiedzi w tym terminie oznacza uznanie reklamacji.</li>
    <li>Wobec Klientów niebędących Konsumentami ani Przedsiębiorcami na prawach konsumenta odpowiedzialność Sprzedawcy z tytułu rękojmi jest wyłączona.</li>
</ol>

<h2>§11 / Dane osobowe</h2>
<ol>
    <li>Administratorem danych osobowych Klientów jest Sprzedawca wskazany w §1.</li>
    <li>Dane przetwarzane są w celu realizacji Zamówień, obsługi reklamacji i zwrotów, prowadzenia dokumentacji księgowej{{ $shop->contactFormEnabled() ? ', odpowiedzi na wiadomości wysłane przez formularz kontaktowy' : '' }} oraz — jeżeli Klient wyrazi na to zgodę — wysyłki informacji handlowych.</li>
    <li>Sklep działa na platformie Kramio, której operator przetwarza dane w imieniu Sprzedawcy jako podmiot przetwarzający. Zasady przetwarzania opisuje Polityka prywatności dostępna w Sklepie.</li>
    <li>Klientowi przysługuje prawo dostępu do danych, ich sprostowania, usunięcia, ograniczenia przetwarzania, przenoszenia, sprzeciwu oraz cofnięcia zgody, a także prawo wniesienia skargi do Prezesa Urzędu Ochrony Danych Osobowych.</li>
</ol>

<h2>§12 / Pozasądowe rozwiązywanie sporów</h2>
<div>Konsument może skorzystać z pozasądowych sposobów rozpatrywania reklamacji i dochodzenia roszczeń, w szczególności z pomocy wojewódzkich inspektoratów Inspekcji Handlowej, stałych sądów polubownych przy Inspekcji Handlowej oraz powiatowych (miejskich) rzeczników konsumentów. Informacje o zasadach dostępu do tych procedur znajdują się na stronie uokik.gov.pl.</div>

<h2>§13 / Zmiany Regulaminu</h2>
<ol>
    <li>Sprzedawca może zmienić Regulamin z ważnych przyczyn, w szczególności zmiany przepisów prawa, zmiany sposobów dostawy lub płatności albo zmian organizacyjnych.</li>
    <li>Do Zamówień złożonych przed wejściem zmiany w życie stosuje się Regulamin w brzmieniu dotychczasowym.</li>
</ol>

<h2>§14 / Postanowienia końcowe</h2>
<ol>
    <li>Umowy zawierane są w języku polskim i podlegają prawu polskiemu. Wybór prawa polskiego nie pozbawia Konsumenta ochrony wynikającej z przepisów, których nie można wyłączyć w drodze umowy, obowiązujących w państwie jego zwykłego pobytu.</li>
    <li>W sprawach nieuregulowanych stosuje się przepisy Kodeksu cywilnego, ustawy o prawach konsumenta oraz ustawy o świadczeniu usług drogą elektroniczną.</li>
    {{-- Data wstawienia wzoru, nie pole do wpisania: sprzedawca i tak wpisałby
         dzisiejszą, a puste pole w opublikowanym regulaminie wygląda źle. Może
         ją zmienić w edytorze, jeśli publikuje później. --}}
    <li>Regulamin obowiązuje od dnia {{ now()->format('d.m.Y') }}.</li>
</ol>

<h2>Załącznik — wzór formularza odstąpienia od umowy</h2>
<div><em>Formularz należy wypełnić i odesłać tylko w przypadku chęci odstąpienia od umowy. Nie jest obowiązkowy — wystarczy każde jednoznaczne oświadczenie.</em></div>
<div>
    Adresat: {{ $sprzedawca }}, {{ $adresZwrotu }}, {{ $dane['email'] }}<br><br>
    Ja/My niniejszym informuję/informujemy o moim/naszym odstąpieniu od umowy sprzedaży następujących rzeczy:<br><br>
    Data zawarcia umowy / odbioru:<br><br>
    Imię i nazwisko konsumenta:<br><br>
    Adres konsumenta:<br><br>
    Podpis (tylko jeżeli formularz jest przesyłany w wersji papierowej):<br><br>
    Data:
</div>
