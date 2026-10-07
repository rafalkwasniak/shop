{{-- DOMYŚLNA TREŚĆ STRONY „ZASADY PUNKTÓW" — szablon deterministyczny.

     Te same zasady co we wzorze regulaminu: zero AI w runtime, opisujemy TYLKO
     to, co sklep faktycznie ma ustawione (limit i minimum pojawiają się tylko,
     gdy są), akapity w <div>, bo HtmlSanitizer wycina <p>.

     Ton jak do klienta („Ty"), bo to strona do przeczytania przed zakupem, nie
     umowa. BEZ procentu zwrotu i bez wyliczeń „ile odzyskasz" (decyzja Rafała
     07.10): stawka w punktach na złotówkę + jeden przelicznik przy wydawaniu.
     Zdania o przeliczeniu i zakończeniu programu opisują to, co robi
     `LoyaltyLedger` — zmieniając silnik, zmieniaj też ten tekst.

     Zmienne: $shop, $earnPhrase, $redeemPhrase, $delay, $validity (null = bez terminu), $maxRedeem, $minRedeem. --}}
<div>W sklepie „{{ $shop->name }}" za każdy zrealizowany zakup dostajesz punkty, które wymienisz na rabat przy kolejnych zamówieniach. Poniżej wszystko, co warto o nich wiedzieć.</div>

<h2>Ile punktów dostajesz</h2>
<ul>
    <li><strong>{{ $earnPhrase }}</strong> Liczymy kwotę faktycznie zapłaconą za produkty — po rabatach, bez kosztu dostawy.</li>
    <li>Przy każdym produkcie widzisz, ile punktów za niego dostaniesz.</li>
</ul>

<h2>Kiedy możesz z nich skorzystać</h2>
@if ($shop->loyaltyDelayDays() > 0)
    <div>Punkty naliczamy, gdy zamówienie zostanie zrealizowane. Do wykorzystania są {{ $delay }} później — to czas na ewentualny zwrot towaru. Do tego dnia widzisz je na koncie jako oczekujące, razem z datą, od której będą dostępne.</div>
@else
    <div>Punkty naliczamy, gdy zamówienie zostanie zrealizowane, i od razu możesz z nich skorzystać.</div>
@endif

<h2>Jak długo są ważne</h2>
@if ($validity)
    <div>Punkty są ważne przez {{ $validity }} od dnia, w którym stają się dostępne. Po tym czasie niewykorzystane punkty wygasają. Przy zakupach najpierw wykorzystujemy te, które wygasają najwcześniej — żadne nie przepadną przez to, że zostały wydane świeższe.</div>
@else
    <div>Punkty nie mają terminu ważności.</div>
@endif

<h2>Jak wykorzystać punkty</h2>
<ol>
    <li>Zaloguj się na swoje konto w sklepie i w koszyku zaznacz „Użyj punktów" — ich wartość odejmiemy od ceny produktów.</li>
    <li>Przy zakupie punkty zamieniamy na rabat: {{ $redeemPhrase }}.</li>
    @if ($maxRedeem)
        <li>Punktami zapłacisz najwyżej {{ $maxRedeem }}% wartości produktów w zamówieniu.</li>
    @endif
    @if ($minRedeem)
        <li>Z punktów skorzystasz, gdy masz ich co najmniej {{ $minRedeem }}.</li>
    @endif
    <li>Punktami nie zapłacisz za dostawę.</li>
    <li>Punktów nie wymieniamy na gotówkę i nie można ich przekazać innej osobie.</li>
</ol>

<h2>Zakupy bez konta</h2>
<div>Punkty zbierają się także za zakupy bez konta — zapisujemy je na adres e-mail podany w zamówieniu. Wystarczy założyć konto w sklepie na ten sam adres, a wszystkie zebrane punkty pojawią się na nim razem z historią zamówień.</div>

<h2>Zwroty i anulowane zamówienia</h2>
<ul>
    <li>Jeśli zamówienie zostanie anulowane, punkty wykorzystane na jego opłacenie wracają na Twoje konto z pierwotnym terminem ważności, a punkty naliczone za to zamówienie odejmujemy.</li>
    <li>Gdy zwracasz część lub całość zamówienia, odejmujemy punkty proporcjonalnie do wartości zwróconych produktów. Jeśli zostały już wydane, saldo może chwilowo spaść poniżej zera — wyrówna się punktami z kolejnych zakupów.</li>
</ul>

<h2>Gdzie sprawdzisz swoje punkty</h2>
<div>Saldo, punkty oczekujące, historię i daty wygaśnięcia znajdziesz w zakładce „Punkty" na swoim koncie w sklepie.</div>

<h2>Zmiany zasad i zakończenie programu</h2>
<div>O zmianach tych zasad informujemy z wyprzedzeniem. Gdy zmienia się wartość punktu, przeliczamy saldo tak, by jego wartość w złotych pozostała taka sama. Jeśli zakończymy program, punkty przestaną być naliczane, ale zebrane wykorzystasz do końca ich ważności.</div>
