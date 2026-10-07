<?php

namespace App\Services;

use App\Enums\MailPriority;
use App\Models\EmailMessage;
use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\Shop;
use App\Support\Vocative;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Przypomnienie „punkty wkrótce wygasną" — raz dziennie z crona
 * (`loyalty:remind`). Jeden mail na klienta i na DZIEŃ WYGAŚNIĘCIA (reguła
 * Rafała 07.10): porcje wygasające tego samego dnia sumujemy w jedną
 * wiadomość, różne dni dostają osobne maile — także wtedy, gdy złapią się
 * w tym samym przebiegu (przerwa crona, pierwsze uruchomienie). Porcje
 * oznaczamy jako przypomniane w tej samej transakcji co mail, więc powtórka
 * komendy nikomu nie wyśle drugiego.
 *
 * Treść jest INFORMACYJNA (stan punktów klienta, bez zachęt do zakupu
 * konkretnych produktów), dlatego nie wymaga zgody marketingowej. Same
 * punkty, bez wartości w zł — zasada prezentacji z 07.10.
 */
class LoyaltyExpiryReminder
{
    public function __construct(private LoyaltyLedger $ledger) {}

    /** Zwraca liczbę wysłanych przypomnień. */
    public function run(): int
    {
        $lots = LoyaltyEntry::query()
            ->where('remaining', '>', 0)
            ->whereNull('expiry_notified_at')
            ->where('available_at', '<=', now())
            ->where('expires_at', '>', now())
            // Dni kalendarzowe, nie sekundy: porcja wygasa o 23:59:59 swojego dnia,
            // więc porównanie z „teraz + 14 dni" co do sekundy przesuwałoby
            // przypomnienie na 13. dzień przed terminem.
            ->where('expires_at', '<=', now()->addDays((int) config('loyalty.reminder_days'))->endOfDay())
            ->get();

        $sent = 0;

        $groups = $lots->groupBy(fn (LoyaltyEntry $lot): string => $lot->shop_id.'|'.$lot->email.'|'.$lot->expires_at->toDateString());

        foreach ($groups as $group) {
            $sent += $this->remind($group) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param  Collection<int, LoyaltyEntry>  $lots  porcje jednego klienta, jednego sklepu, jednego dnia
     */
    private function remind(Collection $lots): bool
    {
        $first = $lots->first();
        $shop = Shop::find($first->shop_id);

        if ($shop === null) {
            return false;
        }

        return DB::transaction(function () use ($lots, $first, $shop): bool {
            // Dług klienta zje wygasające punkty, zanim przepadną (expire() spłaca
            // go najpierw) — przypominamy tylko o tym, co naprawdę zniknie.
            $points = min((int) $lots->sum('remaining'), $this->ledger->balance($shop, $first->email));

            LoyaltyEntry::query()->whereKey($lots->modelKeys())->update(['expiry_notified_at' => now()]);

            if ($points <= 0) {
                return false;
            }

            $this->queueMail($shop, $first->email, $points, $lots->min('expires_at'));

            return true;
        });
    }

    private function queueMail(Shop $shop, string $email, int $points, \DateTimeInterface $expiresAt): void
    {
        $account = $shop->customers()->whereRaw('LOWER(email) = ?', [$email])->first();
        $name = $account?->name ?? Order::query()
            ->where('shop_id', $shop->id)
            ->whereRaw('LOWER(buyer_email) = ?', [$email])
            ->latest('id')
            ->value('buyer_name');
        $date = $expiresAt->format('d.m.Y');
        $host = 'https://'.$shop->host();
        $activated = $account?->isActivated() ?? false;

        // Dłuższa, ciepła treść (Rafał 07.10: pełniejsze maile działają lepiej),
        // ale wciąż o punktach KLIENTA — bez promowania produktów czy ofert,
        // bo wtedy byłaby to informacja handlowa wymagająca zgody.
        $collectsMore = $shop->loyaltyActive();
        $replyLine = filled($shop->contact_email)
            ? 'Masz pytanie? Odpowiedz na tę wiadomość — trafi prosto do sklepu.'
            : null;

        $intro = [
            'W sklepie **'.$shop->name.'** czekają na Ciebie punkty: **'.$points.' pkt** wygaśnie **'.$date.'**.',
            'To rabat, który już masz na koncie. Po tym dniu punkty przepadną i nie da się ich przywrócić, więc szkoda, żeby się zmarnowały.',
        ];

        if ($activated) {
            $intro[] = 'Wykorzystanie ich zajmuje chwilę: dodaj produkty do koszyka, zaloguj się i kliknij „Wykorzystaj". Wartość punktów odejmiemy od ceny od razu, jeszcze przed zapłatą.';
        } else {
            $intro[] = 'Punkty są zapisane na ten adres e-mail. Żeby z nich skorzystać, załóż konto w sklepie na ten sam adres — zajmie to minutę, a wszystkie zebrane punkty od razu pojawią się na koncie.';
            $intro[] = 'Potem wystarczy dodać produkty do koszyka i kliknąć „Wykorzystaj" — wartość punktów odejmiemy od ceny jeszcze przed zapłatą.';
        }

        if ($collectsMore) {
            $intro[] = 'A przy okazji zbierzesz nowe punkty: za opłaconą część zamówienia dostaniesz kolejne — na następne zakupy.';
        }

        $outro = $activated
            ? ['Saldo, punkty oczekujące i daty wygaśnięcia sprawdzisz w Moim koncie, w zakładce „Punkty".', $replyLine]
            : ['Konto przyda się też później: zobaczysz w nim historię zamówień i wszystkie swoje punkty.', $replyLine];

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'from_name' => $shop->name,
            'reply_to' => $shop->contact_email,
            'to_email' => $email,
            'to_name' => trim(($account?->name ?? $name).' '.($account?->surname ?? '')),
            'subject' => 'Twoje punkty wkrótce wygasną — '.$shop->name,
            'preheader' => $points.' pkt wygaśnie '.$date.'. Szkoda, żeby się zmarnowały.',
            'heading' => 'Punkty wkrótce wygasną',
            'greeting' => Vocative::greeting($name),
            'intro_lines' => $intro,
            'action_text' => $activated ? 'Wykorzystaj punkty' : 'Załóż konto i odbierz punkty',
            'action_url' => $activated ? $host : $host.'/rejestracja',
            'outro_lines' => array_values(array_filter($outro)),
        ]);
    }
}
