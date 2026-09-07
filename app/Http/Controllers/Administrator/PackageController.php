<?php

namespace App\Http\Controllers\Administrator;

use App\Http\Controllers\Controller;
use App\Models\PackagePayment;
use App\Support\PackageAttention;
use App\Support\PackageFeatures;
use App\Support\PackageRevenue;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\Request;

/**
 * Konsola admina — pakiety. Przekrój PIENIĘDZY i abonamentów całej platformy:
 * ile wpłynęło, ile jest wart bieżący portfel, kto siedzi w którym pakiecie.
 *
 * Świadomie NIE jest drugą listą sklepów. Pakiet, cena i termin pojedynczego
 * sklepu mają swoje miejsce w dziale „Sklepy" i tam też się je zmienia; tutaj
 * patrzymy poprzecznie, na to, czego z karty jednego sklepu nie widać.
 */
class PackageController extends Controller
{
    public function index(): Renderable
    {
        $attention = PackageAttention::groups();

        return view('administrator.packages.index', [
            'revenue' => PackageRevenue::revenue(),
            'subscriptions' => PackageRevenue::subscriptions(),
            'attention' => $attention,
            // Licznik w nagłówku sumuje POZYCJE, nie grupy: „3 sprawy" mówi, ile
            // rzeczy jest do zrobienia, a „2 grupy" nie mówiłoby nic.
            'attentionCount' => array_sum(array_map(fn (array $group): int => count($group['items']), $attention)),
            // Podgląd rejestru w kolumnie bocznej — kilka ostatnich wpłat wystarczy,
            // żeby zobaczyć, że pieniądze wpływają; po całość idzie się do rejestru.
            'recentPayments' => PackagePayment::query()
                ->where('status', PackagePayment::STATUS_PAID)
                ->with('shop')
                ->orderByDesc('paid_at')
                ->limit(5)
                ->get(),
        ]);
    }

    /**
     * Rejestr opłat — każda złotówka, która przeszła przez platformę: z bramki
     * i z ręki, opłacona i wisząca. To tutaj sprawdza się, czy sprzedawca
     * faktycznie zapłacił i czy dokument do wpłaty istnieje.
     */
    public function payments(Request $request): Renderable
    {
        $filters = [
            'q' => trim((string) $request->query('szukaj', '')),
            'status' => (string) $request->query('status', ''),
            'package' => (string) $request->query('pakiet', ''),
        ];

        $base = PackagePayment::query()
            ->when($filters['q'] !== '', fn ($query) => $query->whereHas(
                'shop',
                fn ($shop) => $shop->where('name', 'like', '%'.$filters['q'].'%')
                    ->orWhere('slug', 'like', '%'.$filters['q'].'%')
            ))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['package'] !== '', fn ($query) => $query->where('target_package', $filters['package']));

        // Suma liczona z TEGO SAMEGO zapytania co lista i tylko z opłaconych:
        // dorzucenie wiszących kazałoby czytać sumę jako pieniądze, którymi nie
        // są. Przy pustych filtrach równa się przychodowi z zestawienia.
        $sum = (float) (clone $base)->where('status', PackagePayment::STATUS_PAID)->sum('amount');

        return view('administrator.packages.payments', [
            'payments' => $base
                ->with(['shop', 'recordedBy'])
                // Wiersz bez daty wpłaty (wiszący) na górę razem z najnowszymi —
                // to on zwykle jest powodem, dla którego ktoś tu zagląda.
                ->orderByRaw('coalesce(paid_at, created_at) desc')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'sum' => $sum,
            // Filtr listy wplat pokazuje pakiety Z OFERTY — za preset spoza niej
            // nikt nie zaplacil i zaplacic nie moze (patrz PackagePaymentRecorder).
            'packages' => PackageFeatures::purchasable(),
        ]);
    }

    /**
     * Formularz rejestracji wpłaty przyjętej poza bramką. Sam zapis prowadzi
     * komponent Livewire — patrz `Administrator\PackagePaymentRecorder`.
     */
    public function recordPayment(): Renderable
    {
        return view('administrator.packages.record');
    }
}
