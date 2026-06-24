# Specyfikacja systemu SaaS dla mikro-sklepów internetowych

## 1. Wprowadzenie

### 1.1 Cel systemu

System jest platformą SaaS umożliwiającą szybkie uruchomienie sklepu internetowego przez osoby oraz małe firmy sprzedające własne produkty.

Grupą docelową są przede wszystkim twórcy rękodzieła, producenci wyrobów wykonywanych na zamówienie oraz niewielkie podmioty prowadzące sprzedaż bez rozbudowanego zaplecza technicznego.

Głównym założeniem projektu jest maksymalne uproszczenie procesu rozpoczęcia sprzedaży. Użytkownik powinien być w stanie utworzyć konto, skonfigurować sklep oraz opublikować pierwszy produkt w czasie nieprzekraczającym 5 minut.

### 1.2 Założenia biznesowe

System powinien:

- minimalizować liczbę danych wymaganych podczas rejestracji,
- ograniczać konfigurację początkową do niezbędnego minimum,
- umożliwiać rozpoczęcie sprzedaży bez wiedzy technicznej,
- oferować gotowy do działania sklep natychmiast po aktywacji konta,
- zapewniać możliwość dalszej rozbudowy funkcjonalności bez konieczności przebudowy architektury.

### 1.3 Model działania

Platforma działa w modelu SaaS (Software as a Service).

Każdy sprzedawca posiada własny sklep działający w ramach wspólnej platformy. Sklepy są od siebie logicznie odseparowane i posiadają własne:

- produkty,
- klientów,
- zamówienia,
- ustawienia,
- regulaminy.

Domyślnie każdy sklep otrzymuje własną subdomenę w domenie platformy.

### 1.4 Kluczowe wyróżniki produktu

Platforma została zaprojektowana z myślą o osobach, które chcą rozpocząć sprzedaż własnych produktów bez konieczności przechodzenia przez rozbudowany proces konfiguracji sklepu.

Głównym wyróżnikiem systemu jest możliwość uruchomienia gotowego sklepu internetowego w ciągu kilku minut od rejestracji.

Proces uruchomienia sklepu obejmuje:

1. Rejestrację użytkownika.
2. Aktywację konta.
3. Uzupełnienie podstawowych danych sklepu.
4. Dodanie pierwszego produktu.
5. Automatyczne opublikowanie sklepu.

Docelowym efektem jest możliwość rozpoczęcia sprzedaży jeszcze przed zakończeniem konfiguracji wszystkich opcjonalnych ustawień.

Robocze hasło projektu:

> Kiedy konkurencja dopiero konfiguruje swój sklep, Ty już sprzedajesz swój produkt.

### 1.5 Zakres platformy

System nie pełni funkcji marketplace.

Każdy sklep działa jako niezależny podmiot posiadający własnych klientów, produkty oraz zamówienia.

Klienci nie mają możliwości przeglądania innych sklepów działających w ramach platformy.

Platforma może posiadać własną stronę główną prezentującą produkt oraz opcjonalnie promującą wybrane sklepy, jednak sklepy nie są ze sobą bezpośrednio powiązane.

### 1.6 Pakiety i limity

Architektura systemu musi umożliwiać definiowanie pakietów funkcjonalnych oraz limitów.

Wszystkie limity ilościowe powinny być przechowywane w konfiguracji systemu i nie mogą być zapisane na stałe w kodzie aplikacji.

Przykładowe limity:

- maksymalna liczba produktów,
- maksymalna liczba zdjęć produktu,
- dostępność wybranych metod płatności,
- dostępność wybranych metod dostawy.

W wersji MVP dostępny jest wyłącznie pakiet Free.

Pakiet Free umożliwia publikację do 25 produktów.

Mechanizm pakietów powinien być przygotowany na przyszłe rozszerzenie o kolejne warianty abonamentowe.

### Produkty cyfrowe

System dopuszcza sprzedaż produktów cyfrowych.

W wersji MVP platforma nie odpowiada za przechowywanie ani automatyczne dostarczanie plików kupującemu.

Po złożeniu zamówienia realizacja dostawy produktu cyfrowego pozostaje po stronie sprzedawcy.

Takie podejście umożliwia sprzedaż materiałów przygotowywanych indywidualnie dla klienta, np. plików PDF zawierających personalizowane dane lub znaki identyfikujące nabywcę.

### Zdjęcia produktu

Produkt może posiadać:

- 1 zdjęcie główne,
- maksymalnie 4 zdjęcia dodatkowe.

Łącznie produkt może posiadać maksymalnie 5 zdjęć.

Po przesłaniu zdjęcia system automatycznie:

- optymalizuje plik,
- usuwa nadmiarowe metadane,
- zmniejsza rozdzielczość.

Maksymalny wymiar dłuższego boku zdjęcia wynosi 1600 px.

Oryginalne pliki nie są przechowywane.

### Tagi produktów

Produkt może posiadać dowolną liczbę tagów.

Tagi służą do:

- grupowania produktów,
- budowy filtrów,
- tworzenia chmury tagów w sklepie.

Przykłady tagów:

- biżuteria,
- srebro,
- ślub,
- prezent,
- rękodzieło.

Tagi nie posiadają struktury hierarchicznej.

### Warianty produktów

System nie obsługuje wariantów produktów.

Każdy produkt stanowi osobną pozycję sprzedażową posiadającą własną nazwę, cenę, opis oraz zdjęcia.

Przykład:

Koszulka w 3 kolorach i 5 rozmiarach powinna zostać dodana jako 15 niezależnych produktów.

### Założenia SEO

Platforma musi generować strony zgodne z aktualnymi dobrymi praktykami SEO.

W szczególności:

- semantyczna struktura HTML,
- indywidualne tytuły stron,
- indywidualne meta opisy,
- przyjazne adresy URL,
- poprawna struktura nagłówków,
- automatyczne mapy strony (sitemap),
- poprawne dane Open Graph,
- poprawne dane Schema.org dla produktów.

Wszystkie kluczowe elementy SEO powinny być generowane automatycznie na podstawie danych wprowadzanych przez sprzedawcę.




## Strony informacyjne sklepu

### Założenia

System nie posiada mechanizmu dowolnego tworzenia stron CMS.

Dostępny jest zamknięty zestaw stron informacyjnych wystarczający do prowadzenia sprzedaży internetowej oraz spełnienia wymogów prawnych.

Takie podejście upraszcza konfigurację sklepu oraz ogranicza ilość treści wymaganych od sprzedawcy podczas uruchamiania sprzedaży.

### Strona główna

Strona główna sklepu nie jest stroną treściową.

Jej głównym celem jest prezentacja oferty sprzedawcy.

Na stronie głównej mogą być prezentowane:

- produkty oznaczone jako wyświetlane na stronie głównej,
- najnowsze produkty,
- opis sklepu,
- logo sklepu,
- podstawowe informacje o sprzedawcy.

Opis sklepu jest pobierany z ustawień sklepu.

W konfiguracji produktu dostępna jest opcja:

```text
[ ] Wyświetl na stronie głównej
```

Pozwala ona oznaczyć produkty, które mają być dodatkowo eksponowane na stronie głównej sklepu.

### Strona „O nas”

Każdy sklep posiada stronę „O nas”.

Treść strony jest edytowalna przez sprzedawcę.

Podczas tworzenia sklepu system może automatycznie wygenerować domyślną treść na podstawie danych podanych podczas rejestracji.

### Strona „Dostawa i płatności”

Każdy sklep posiada stronę „Dostawa i płatności”.

Strona zawiera informacje dotyczące:

- dostępnych metod płatności,
- sposobów odbioru zamówień,
- czasu realizacji zamówień,
- procedury zwrotów.

Podczas tworzenia sklepu system automatycznie generuje domyślną treść, którą sprzedawca może później zmodyfikować.

### Regulamin sklepu

Każdy sklep posiada własny regulamin.

Podczas tworzenia sklepu dodawana jest domyślna wersja regulaminu wykorzystująca dane sprzedawcy.

Regulamin może być dowolnie modyfikowany przez właściciela sklepu.

### Polityka prywatności

Każdy sklep posiada politykę prywatności opartą o szablon dostarczany przez platformę.

Sprzedawca może dostosować jej treść do własnych potrzeb.

### Polityka cookies

Każdy sklep posiada stronę polityki cookies generowaną na podstawie ustawień systemowych oraz aktywnych integracji.

### Kontakt

Sklep nie posiada dedykowanej strony kontaktowej.

Adres e-mail sprzedawcy prezentowany jest w stopce sklepu.

Kliknięcie adresu powoduje uruchomienie domyślnego klienta pocztowego użytkownika.

## Ustawienia zaawansowane sklepu

Panel sklepu zawiera sekcję „Ustawienia zaawansowane”.

Sekcja przeznaczona jest do konfiguracji integracji zewnętrznych oraz funkcji technicznych.

### Integracje

W zależności od posiadanego pakietu mogą być dostępne między innymi:

- integracje InPost,
- integracje operatorów płatności online,
- integracje systemów analitycznych.

### Google Analytics

Sprzedawca może podać identyfikator Google Analytics.

Po zapisaniu konfiguracji kod śledzący jest automatycznie dodawany do wszystkich stron sklepu.

Architektura modułu powinna umożliwiać w przyszłości dodawanie kolejnych narzędzi analitycznych bez konieczności przebudowy systemu.

## Zarządzanie dostępnością produktów

### Kontrola stanów magazynowych

Domyślnie każdy produkt posiada kontrolę stanów magazynowych.

Podczas tworzenia produktu sprzedawca określa ilość dostępnych sztuk.

Przykład:

```text
Stan magazynowy: 10 szt.
```

Po wyczerpaniu dostępnej ilości produkt staje się niedostępny do zakupu.

### Sprzedaż bez limitu

Sprzedawca może wyłączyć kontrolę stanów magazynowych.

W takim przypadku produkt może być sprzedawany bez ograniczeń ilościowych.

Przykładowe zastosowania:

- produkty wykonywane na zamówienie,
- usługi,
- produkty cyfrowe,
- produkty dostępne stale.

W formularzu produktu dostępna jest opcja:

```text
[ ] Kontroluj stan magazynowy
```

Po wyłączeniu tej opcji pole ilości nie jest wymagane.

## Widoczność sklepu

### Automatyczna publikacja sklepu

Sklep staje się publicznie dostępny po spełnieniu następujących warunków:

- sklep został aktywowany,
- istnieje przynajmniej jeden aktywny produkt dostępny do wyświetlania.

Dodanie pierwszego produktu jest elementem rekomendowanego procesu uruchomienia sklepu.

### Automatyczne ukrywanie sklepu

Sklep automatycznie przechodzi w tryb niedostępny, gdy:

- wszystkie produkty zostaną usunięte,
- wszystkie produkty zostaną ukryte,
- nie istnieje żaden aktywny produkt możliwy do wyświetlenia.

W takim przypadku sklep nie jest dostępny publicznie.

Osoby odwiedzające sklep widzą stronę informacyjną z komunikatem o przygotowywaniu oferty.

Przykładowy komunikat:

> Już wkrótce udostępnimy nasze produkty. Zapraszamy ponownie.

Administrator oraz właściciel sklepu zachowują dostęp do panelu zarządzania.

## Zarządzanie produktami

### Widoczność produktu

Każdy produkt może zostać oznaczony jako:

- aktywny,
- ukryty.

Ukryty produkt:

- nie jest wyświetlany w sklepie,
- nie jest dostępny w wynikach wyszukiwania,
- nie może zostać zakupiony.

### Usuwanie produktu

System obsługuje dwa rodzaje usuwania produktów.

#### Usunięcie trwałe

Produkt może zostać usunięty bezpowrotnie, jeżeli nigdy nie wystąpił w żadnym zamówieniu.

#### Usunięcie logiczne

Jeżeli produkt został kiedykolwiek zakupiony, usunięcie powoduje jedynie jego logiczne oznaczenie jako usuniętego.

Produkt:

- pozostaje dostępny w historii zamówień,
- nie jest widoczny w sklepie,
- nie może zostać ponownie zakupiony.

Takie rozwiązanie zapewnia spójność historycznych danych sprzedażowych.

## Weryfikacja dostępności produktów

System powinien weryfikować dostępność produktów na każdym etapie procesu zakupowego.

W szczególności kontrola wykonywana jest podczas:

- dodawania produktu do koszyka,
- wyświetlania koszyka,
- przechodzenia do formularza zamówienia,
- składania zamówienia.

### Automatyczna aktualizacja koszyka

Jeżeli dostępność produktu ulegnie zmianie, system automatycznie aktualizuje zawartość koszyka.

Przykład:

Klient posiada w koszyku 5 sztuk produktu.

W międzyczasie dostępne pozostają jedynie 2 sztuki.

System automatycznie zmniejsza ilość produktu w koszyku do 2 sztuk i wyświetla odpowiedni komunikat.

Przykładowy komunikat:

> Ilość produktu została automatycznie dostosowana do aktualnej dostępności magazynowej.

### Produkty niedostępne

Jeżeli produkt stał się niedostępny przed złożeniem zamówienia:

- zostaje automatycznie usunięty z koszyka,
- klient otrzymuje informację o zmianie.

Przykładowy komunikat:

> Jeden lub więcej produktów nie jest już dostępnych i został usunięty z koszyka.

### Produkty wyprzedane

Produkt z aktywną kontrolą stanów magazynowych pozostaje widoczny w sklepie po wyczerpaniu stanu magazynowego.

Na stronie produktu wyświetlana jest informacja o braku dostępności.

Produkt:

- nie może zostać dodany do koszyka,
- nie może zostać zakupiony,
- pozostaje dostępny do przeglądania.

### Rezerwacja produktów

W wersji MVP koszyk nie rezerwuje stanów magazynowych.

Dodanie produktu do koszyka nie wpływa na jego dostępność dla innych klientów.

Dostępność produktu jest każdorazowo weryfikowana podczas:

- wyświetlania koszyka,
- przechodzenia do formularza zamówienia,
- składania zamówienia.

Takie rozwiązanie zapobiega sytuacjom, w których produkty pozostają zablokowane przez klientów, którzy nie finalizują zakupu.

### Finalna weryfikacja zamówienia

Bezpośrednio przed utworzeniem zamówienia system wykonuje ponowną kontrolę dostępności wszystkich pozycji.

Jeżeli:

- produkt nie jest już dostępny,
- ilość produktu jest mniejsza od zamawianej,

system automatycznie aktualizuje zawartość koszyka oraz informuje klienta o zmianach.

Zamówienie nie może zostać utworzone dla ilości przekraczającej aktualny stan magazynowy.

### Priorytety projektowe

Podczas projektowania funkcjonalności priorytet mają:

1. Prostota obsługi.
2. Szybkość konfiguracji sklepu.
3. Czytelność dla sprzedawcy.
4. Czytelność dla klienta końcowego.

System świadomie rezygnuje z części zaawansowanych funkcji spotykanych w dużych platformach e-commerce, jeżeli ich obecność zwiększa złożoność obsługi bez istotnych korzyści dla grupy docelowej.

## Statusy zamówień

System wykorzystuje wspólny zestaw statusów zamówień dla wszystkich pakietów.

Poszczególne pakiety mogą wykorzystywać jedynie część dostępnych statusów.

### Statusy podstawowe

| Status | Opis |
|----------|----------|
| Nowe | Zamówienie zostało złożone przez klienta |
| Oczekuje na płatność | Zamówienie oczekuje na zaksięgowanie płatności |
| Opłacone | Płatność została potwierdzona |
| W realizacji | Zamówienie jest przygotowywane |
| Gotowe do odbioru | Zamówienie oczekuje na odbiór osobisty |
| Wysłane | Zamówienie zostało nadane |
| Zrealizowane | Zamówienie zostało zakończone |
| Anulowane | Zamówienie zostało anulowane |

### Powiadomienia

Każda zmiana statusu powoduje automatyczne wysłanie wiadomości e-mail do klienta.

Treść wiadomości generowana jest na podstawie odpowiedniego szablonu systemowego.

## Szybka obsługa zamówień z poziomu wiadomości e-mail

Wiadomości wysyłane do sprzedawcy mogą zawierać akcje umożliwiające zmianę statusu zamówienia bez konieczności logowania do panelu administracyjnego.

Każda akcja realizowana jest za pomocą jednorazowego, bezpiecznego linku zawierającego token autoryzacyjny.

### Pakiet Free

W przypadku zamówień opłacanych:

- odbiorem osobistym,
- przelewem tradycyjnym,

wiadomość e-mail o nowym zamówieniu zawiera przycisk:

```text
Realizuj zamówienie
```

Kliknięcie przycisku:

- zmienia status zamówienia na „W realizacji”,
- zapisuje operację w historii zamówienia,
- wysyła klientowi wiadomość o rozpoczęciu realizacji.

### Pakiety z płatnościami online

W przypadku zamówień opłacanych online przycisk aktywuje się dopiero po potwierdzeniu płatności przez operatora płatności.

Dzięki temu sprzedawca nie rozpoczyna realizacji nieopłaconych zamówień.

### Przejścia statusów

Sprzedawca może przechodzić pomiędzy statusami zgodnie z charakterem zamówienia.

Przykładowe ścieżki:

#### Odbiór osobisty

Nowe
→ W realizacji
→ Gotowe do odbioru
→ Zrealizowane

#### Przesyłka kurierska

Nowe
→ W realizacji
→ Wysłane
→ Zrealizowane

#### Zamówienie anulowane

Nowe
→ Anulowane

W realizacji
→ Anulowane

## Historia zamówienia

Każde zamówienie posiada historię zdarzeń.

Historia służy do rejestrowania wszystkich istotnych operacji wykonanych na zamówieniu.

Każde zdarzenie zawiera:

- datę i godzinę,
- typ zdarzenia,
- opis zdarzenia,
- użytkownika lub system odpowiedzialny za wykonanie operacji.

### Przykładowe zdarzenia

- utworzenie zamówienia,
- zmiana statusu zamówienia,
- anulowanie zamówienia,
- potwierdzenie płatności,
- wysłanie zamówienia,
- przygotowanie do odbioru,
- wysłanie wiadomości e-mail,
- błąd wysyłki wiadomości e-mail.

### Przykładowa historia

```text
12.06.2026 10:15
Zamówienie utworzone

12.06.2026 10:15
Wysłano wiadomość „Potwierdzenie zamówienia”

12.06.2026 10:22
Status zmieniono na „W realizacji”

13.06.2026 09:14
Status zmieniono na „Gotowe do odbioru”
```

Historia zamówienia stanowi element dokumentacyjny oraz pomocniczy podczas obsługi klienta i reklamacji.


## Historia komunikacji

System zapisuje informacje o wszystkich wiadomościach wysłanych w związku z zamówieniem.

Dla każdej wiadomości przechowywane są:

- data wysyłki,
- odbiorca,
- temat wiadomości,
- identyfikator szablonu,
- status dostarczenia,
- pełna wyrenderowana treść wiadomości.

### Podgląd wiadomości

Sprzedawca może wyświetlić dokładną treść wiadomości wysłanej do klienta.

Treść prezentowana jest w formacie HTML identycznym z wysłaną wiadomością.

Podgląd ma charakter informacyjny i nie umożliwia modyfikacji zapisanej wiadomości.


## Numeracja zamówień

Każde zamówienie posiada dwa identyfikatory:

### Identyfikator techniczny

Identyfikator techniczny służy do wewnętrznej obsługi systemu oraz relacji w bazie danych.

Identyfikator nie jest prezentowany klientom ani sprzedawcom.

### Numer zamówienia

Każdy sklep prowadzi własną, niezależną numerację zamówień.

Numeracja rozpoczyna się od wartości 1 dla każdego nowo utworzonego sklepu.

Przykład:

```text
Sklep A

1
2
3
4

Sklep B

1
2
3
4
```

Numer zamówienia jest prezentowany:

- klientowi,
- sprzedawcy,
- w wiadomościach e-mail,
- w dokumentach generowanych przez system.

### Ciągłość numeracji

Numery zamówień nie są ponownie wykorzystywane.

Anulowanie zamówienia nie powoduje zwolnienia numeru.

Usunięcie logiczne zamówienia nie powoduje zwolnienia numeru.

Numeracja pozostaje ciągła i jednoznaczna w obrębie sklepu.

## Usuwanie zamówień

Zamówienia nie mogą zostać usunięte fizycznie z systemu.

System wykorzystuje wyłącznie usuwanie logiczne.

Usunięte zamówienia:

- pozostają zapisane w bazie danych,
- zachowują historię zdarzeń,
- zachowują historię komunikacji,
- zachowują przypisane numery zamówień.

Takie podejście zapewnia spójność danych oraz zgodność z wymaganiami dokumentacyjnymi i księgowymi.

## Dane adresowe sklepu

Każdy sklep musi posiadać adres prowadzenia działalności lub adres kontaktowy.

Adres jest wymagany podczas procesu aktywacji sklepu.

Dane adresowe przechowywane są w osobnych polach umożliwiających walidację oraz późniejsze wykorzystanie w dokumentach i integracjach.

Przykładowe pola:

- kraj,
- województwo,
- miejscowość,
- kod pocztowy,
- ulica,
- numer budynku,
- numer lokalu.

### Zastosowanie adresu

Dane adresowe wykorzystywane są między innymi w:

- regulaminie sklepu,
- polityce prywatności,
- danych kontaktowych,
- informacjach o odbiorze osobistym,
- dokumentach sprzedażowych,
- przyszłych integracjach z systemami zewnętrznymi.

### Widoczność adresu

Adres sklepu jest publicznie dostępny dla klientów sklepu i stanowi element budujący wiarygodność sprzedawcy.

### Integracje księgowe

Architektura systemu powinna umożliwiać integrację z zewnętrznymi systemami fakturowania.

Planowaną integracją jest:

- Fakturownia.

Integracja powinna umożliwiać między innymi:

- automatyczne tworzenie dokumentów sprzedażowych,
- pobieranie numerów dokumentów,
- powiązanie dokumentów z zamówieniami,
- automatyzację procesów księgowych.

Funkcjonalność nie jest częścią MVP.

## Numer telefonu

### Sprzedawca

Numer telefonu jest wymagany podczas aktywacji sklepu.

Numer wykorzystywany jest między innymi do:

- kontaktu z klientami,
- organizacji odbiorów osobistych,
- przyszłych integracji logistycznych,
- odzyskiwania dostępu do konta.

Sprzedawca może zdecydować, czy numer telefonu będzie publicznie widoczny w sklepie.

### Klient

Numer telefonu jest wymagany podczas składania zamówienia oraz podczas rejestracji konta klienta.

Numer wykorzystywany jest między innymi do:

- kontaktu w sprawie zamówienia,
- wyjaśniania niejasności dotyczących zamówienia,
- przyszłych integracji logistycznych,
- powiadomień związanych z realizacją zamówienia.

### Filozofia produktu

MyShop nie próbuje być pełnym systemem e-commerce.

System koncentruje się na umożliwieniu szybkiego rozpoczęcia sprzedaży przez małych producentów i twórców.

W przypadku konfliktu pomiędzy prostotą obsługi a rozbudowaną funkcjonalnością priorytetem jest prostota obsługi.

## Dane do zamówienia

### Domyślne dane klienta

Klient może przechowywać w swoim profilu:

- imię,
- nazwisko,
- adres e-mail,
- numer telefonu,
- adres.

Dane te są automatycznie uzupełniane podczas składania zamówienia.

### Zakup jako firma

Podczas składania zamówienia klient może oznaczyć zakup jako firmowy.

Przykład:

```text
[ ] Kupuję jako firma
```

Po zaznaczeniu opcji wyświetlane są dodatkowe pola:

- nazwa firmy,
- NIP.

W przyszłości lista pól może zostać rozszerzona o dodatkowe dane wymagane przez integracje księgowe.

### Jednorazowe dane firmy

Dane firmy podane podczas składania zamówienia dotyczą wyłącznie bieżącego zamówienia.

Klient może opcjonalnie zapisać je jako dane domyślne dla kolejnych zamówień.

Przykład:

```text
[ ] Zapisz dane firmy w profilu
```

### Jednorazowy adres

Podczas składania zamówienia klient może podać adres inny niż zapisany w profilu.

Zmiana adresu nie powoduje automatycznej aktualizacji danych profilowych.

Klient może opcjonalnie zapisać nowy adres jako domyślny.

Przykład:

```text
[ ] Zapisz adres w profilu
```
## Rejestracja klienta

### Zakup bez rejestracji

Klient może złożyć zamówienie bez zakładania konta.

Do złożenia zamówienia wymagane są wyłącznie dane niezbędne do realizacji zamówienia.

### Utworzenie konta podczas składania zamówienia

Podczas składania zamówienia klient może opcjonalnie utworzyć konto.

Przykład:

```text
[ ] Utwórz konto
```

Po zaznaczeniu opcji wyświetlane są dodatkowe pola wymagane do utworzenia konta.

Minimalnie:

- hasło,
- potwierdzenie hasła.

Adres e-mail podany podczas składania zamówienia staje się adresem logowania.

### Powiązanie wcześniejszych zamówień

Jeżeli klient utworzy konto przy użyciu adresu e-mail, który występował wcześniej w zamówieniach danego sklepu, system automatycznie przypisuje wcześniejsze zamówienia do nowo utworzonego konta.

Dzięki temu klient uzyskuje dostęp do pełnej historii swoich zakupów, również tych wykonanych przed rejestracją.

Mechanizm działa wyłącznie w obrębie danego sklepu.

## Logowanie i uwierzytelnianie

### Sprzedawcy

Rejestracja oraz logowanie sprzedawców odbywa się wyłącznie w ramach platformy MyShop.

Dostępne są między innymi:

- rejestracja konta,
- logowanie,
- odzyskiwanie hasła,
- aktywacja sklepu,
- panel zarządzania sklepem.

Panel sprzedawcy nie jest dostępny z poziomu sklepu internetowego.

### Klienci sklepów

Każdy sklep posiada własny system rejestracji i logowania klientów.

Klienci logują się wyłącznie w obrębie konkretnego sklepu.

Przykład:

```text
https://sklepanna.myshop.pl/login
```

Po zalogowaniu klient uzyskuje dostęp wyłącznie do:

- swoich danych,
- swoich adresów,
- swojej historii zamówień,

powiązanych z danym sklepem.

### Separacja klientów pomiędzy sklepami

Konta klientów są od siebie całkowicie odseparowane.

Ten sam adres e-mail może występować w wielu sklepach.

Posiadanie konta w jednym sklepie nie powoduje utworzenia konta w innych sklepach działających w ramach platformy.

### Brak globalnego konta klienta

Platforma nie udostępnia centralnego panelu klienta obejmującego wiele sklepów.

Każdy sklep zarządza własną bazą klientów oraz własnym procesem logowania.

## Rejestracja klienta

### Rejestracja podstawowa

Klient może założyć konto w sklepie podając:

- imię,
- adres e-mail.

Po wysłaniu formularza system wysyła wiadomość aktywacyjną na podany adres e-mail.

### Aktywacja konta

Po kliknięciu w link aktywacyjny klient uzupełnia wymagane dane konta:

- nazwisko,
- hasło,
- numer telefonu.

Po zapisaniu formularza konto zostaje aktywowane.

### Dane dodatkowe

Dane dodatkowe nie są wymagane podczas aktywacji konta.

Mogą zostać uzupełnione później:

- w profilu klienta,
- podczas składania zamówienia.

Do danych dodatkowych należą między innymi:

- adres,
- dane firmy,
- NIP.

### Minimalizacja danych

Proces rejestracji powinien wymagać wyłącznie danych niezbędnych do utworzenia i aktywacji konta.

Pozostałe informacje zbierane są dopiero w momencie, gdy są rzeczywiście potrzebne.

## Tworzenie konta podczas składania zamówienia

Podczas składania zamówienia klient może zaznaczyć opcję:

```text
[ ] Utwórz konto
```

W takim przypadku system zapisuje informację o chęci utworzenia konta i wysyła wiadomość aktywacyjną na podany adres e-mail.

Do momentu aktywacji konto pozostaje nieaktywne.

Proces aktywacji przebiega identycznie jak w przypadku standardowej rejestracji klienta.

### Powiązanie zamówień

Po aktywacji konta system automatycznie wyszukuje wszystkie zamówienia przypisane do tego samego adresu e-mail w obrębie danego sklepu.

Odnalezione zamówienia zostają przypisane do nowo utworzonego konta klienta.

Dzięki temu klient uzyskuje dostęp do pełnej historii zakupów wykonanych przed rejestracją.

Mechanizm działa wyłącznie w obrębie danego sklepu.

## Adresy URL

### Założenia

System powinien generować przyjazne adresy URL wspierające pozycjonowanie w wyszukiwarkach.

### Produkty

Adres produktu powinien zawierać:

- identyfikator produktu,
- przyjazny adres SEO wygenerowany na podstawie nazwy produktu.

Przykład:

```text
/produkt/143-naszyjnik-srebrny
```

Identyfikator produktu stanowi podstawowy element identyfikacji rekordu.

Część tekstowa adresu pełni funkcję informacyjną i wspiera SEO.

### Stabilność adresów

Zmiana nazwy produktu nie może powodować utraty możliwości odnalezienia produktu pod istniejącym adresem.

System powinien wykorzystywać identyfikator produktu jako główny element wyszukiwania rekordu.

### Status

Szczegółowa struktura adresów URL wymaga doprecyzowania na etapie projektowania SEO oraz routingu aplikacji.

## Historia zamówień klienta

Po zalogowaniu klient uzyskuje dostęp do historii swoich zamówień.

Lista zamówień zawiera podstawowe informacje:

- numer zamówienia,
- datę złożenia,
- aktualny status,
- wartość zamówienia.

### Szczegóły zamówienia

Klient może wyświetlić szczegóły wybranego zamówienia.

Widok szczegółów obejmuje między innymi:

- dane zamówienia,
- zamówione produkty,
- historię zmian statusów,
- historię komunikacji związanej z zamówieniem.

### Bezpieczeństwo

Klient może uzyskać dostęp wyłącznie do zamówień przypisanych do jego konta.

Próba otwarcia zamówienia należącego do innego klienta powinna zostać zablokowana przez system.

Mechanizm autoryzacji działa niezależnie od numeracji zamówień wykorzystywanej przez sklep.

## Dostawy

### Konfiguracja dostaw

Metody dostawy definiowane są na poziomie sklepu.

Raz skonfigurowane metody dostawy są automatycznie dostępne dla wszystkich produktów w sklepie.

Konfiguracja dostaw nie jest wykonywana na poziomie pojedynczego produktu.

Takie rozwiązanie upraszcza proces dodawania produktów oraz konfigurację sklepu.

### Dostępne metody dostawy

W wersji MVP dostępne są:

- odbiór osobisty,
- dostawy definiowane ręcznie przez sprzedawcę.

Przykładowe metody:

- Odbiór osobisty,
- Kurier lokalny,
- Kurier krajowy,
- Dostawa własna.

### Metoda dostawy

Każda metoda dostawy posiada:

- nazwę,
- opis (opcjonalny),
- koszt dostawy,
- status aktywna/nieaktywna.

### Odbiór osobisty

Odbiór osobisty może zostać włączony lub wyłączony przez sprzedawcę.

Adres odbioru pobierany jest z danych sklepu.

W przypadku zamówień realizowanych przez odbiór osobisty dostępny jest status:

```text
Gotowe do odbioru
```

Zmiana statusu powoduje wysłanie wiadomości e-mail do klienta.

## Lokalizacja systemu

### Język systemu

W wersji MVP system dostępny jest wyłącznie w języku polskim.

Dotyczy to między innymi:

- panelu sprzedawcy,
- sklepów internetowych,
- formularzy,
- wiadomości systemowych,
- szablonów wiadomości e-mail,
- dokumentacji użytkownika.

Architektura aplikacji powinna umożliwiać dodanie kolejnych wersji językowych w przyszłości bez konieczności przebudowy systemu.

### Waluta

W wersji MVP wszystkie ceny prezentowane są wyłącznie w polskich złotych (PLN).

System nie obsługuje wielu walut.

Architektura systemu powinna umożliwiać dodanie obsługi kolejnych walut w przyszłych wersjach produktu.

## Ceny i podatki

### Cena produktu

Cena produktu wprowadzana jest jako cena brutto.

Cena brutto stanowi cenę prezentowaną klientowi w sklepie.

### Stawka VAT

Każdy produkt posiada przypisaną stawkę VAT.

Dostępne stawki VAT definiowane są przez system i mogą być rozszerzane zgodnie z obowiązującymi przepisami.

Przykładowe stawki:

- 23%
- 8%
- 5%
- 0%
- zw

### Domyślna stawka VAT

Sprzedawca może określić domyślną stawkę VAT dla swojego sklepu.

Podczas tworzenia produktu system automatycznie przypisuje domyślną stawkę VAT.

Sprzedawca może ją zmienić dla pojedynczego produktu.

### Wyliczenia

Na podstawie ceny brutto oraz stawki VAT system automatycznie wylicza:

- wartość netto,
- wartość podatku VAT.

Wyliczenia wykonywane są automatycznie przez system i nie wymagają dodatkowych działań ze strony sprzedawcy.

### Integracje księgowe

Informacje o cenie brutto, cenie netto oraz stawce VAT wykorzystywane są przez przyszłe integracje księgowe, w szczególności integrację z Fakturownią.

## Dokumenty sprzedażowe

### Założenia

Obsługa dokumentów sprzedażowych nie jest częścią wersji MVP.

Funkcjonalność zostanie dostarczona poprzez integrację z zewnętrznym systemem księgowym.

Planowaną integracją jest Fakturownia.

### Integracja księgowa

System powinien umożliwiać przekazywanie do systemu księgowego między innymi:

- numeru zamówienia,
- danych klienta,
- danych firmy klienta,
- pozycji zamówienia,
- cen netto,
- cen brutto,
- stawek VAT,
- wartości podatku VAT.

### Powiązanie dokumentów

Architektura systemu powinna umożliwiać powiązanie zamówienia z dokumentem sprzedażowym wygenerowanym przez system księgowy.

Powiązanie powinno umożliwiać późniejszy podgląd numeru dokumentu oraz jego statusu.

### Konfiguracja sklepu

W przyszłości właściciel sklepu będzie mógł określić sposób obsługi dokumentów sprzedażowych.

Przykładowe warianty:

- automatyczne wystawianie dokumentów dla wszystkich klientów,
- automatyczne wystawianie dokumentów wyłącznie dla firm,
- ręczne wystawianie dokumentów,
- brak integracji księgowej.

Szczegółowy proces obsługi dokumentów sprzedażowych zostanie określony podczas projektowania integracji księgowej.

## Zgody i akceptacje

### Założenia

System rejestruje wszystkie obowiązkowe zgody wymagane podczas:

- rejestracji sprzedawcy,
- rejestracji klienta,
- składania zamówienia.

### Sprzedawca

Podczas rejestracji sprzedawca musi zaakceptować:

- regulamin platformy,
- politykę prywatności platformy.

Bez wyrażenia wymaganych zgód utworzenie konta nie jest możliwe.

### Klient

Podczas rejestracji klient musi zaakceptować:

- regulamin sklepu,
- politykę prywatności.

Bez wyrażenia wymaganych zgód utworzenie konta nie jest możliwe.

### Zamówienie bez rejestracji

Podczas składania zamówienia klient musi zaakceptować:

- regulamin sklepu,
- politykę prywatności.

Bez wyrażenia wymaganych zgód złożenie zamówienia nie jest możliwe.

### Rejestrowanie zgód

Dla każdej zaakceptowanej zgody system zapisuje:

- identyfikator dokumentu,
- wersję dokumentu,
- datę i godzinę akceptacji,
- identyfikator użytkownika lub zamówienia.

Informacje o zaakceptowanych zgodach nie mogą być usuwane.

## Dokumenty prawne

### Dokumenty platformy

Platforma udostępnia własne dokumenty prawne obowiązujące wszystkich użytkowników systemu.

Do dokumentów platformy należą między innymi:

- polityka prywatności,
- polityka cookies,
- regulamin platformy.

### Dokumenty sklepu

Każdy sklep posiada własne dokumenty regulujące proces sprzedaży.

Do dokumentów sklepu należą między innymi:

- regulamin sklepu,
- informacje o dostawie i płatnościach.

### Wersjonowanie dokumentów

System powinien umożliwiać przechowywanie kolejnych wersji dokumentów prawnych.

Akceptacja dokumentów przez użytkowników powinna być powiązana z konkretną wersją dokumentu obowiązującą w momencie wyrażenia zgody.


## Funkcje AI

Platforma wykorzystuje model DeepSeek do wspomagania tworzenia treści.

### Generowanie treści

Użytkownik może wygenerować treść dla:

- opisu produktu,
- opisu sklepu,
- strony „O nas”,
- regulaminów i treści informacyjnych.

Po uruchomieniu generatora użytkownik podaje:

- założenia treści,
- preferowaną długość tekstu.

Wygenerowana odpowiedź jest automatycznie wstawiana do edytowanego pola.

### Redakcja treści

Dla wybranych pól dostępna jest funkcja „Zredaguj przez AI”.

Funkcja służy do:

- poprawy błędów językowych,
- poprawy interpunkcji,
- poprawy stylistyki,
- ujednolicenia formatowania.

Funkcja nie powinna znacząco zmieniać znaczenia tekstu.