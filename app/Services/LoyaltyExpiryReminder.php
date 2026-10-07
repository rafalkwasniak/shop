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
 * (`loyalty:remind`). Jeden mail na klienta: suma punktów z porcji, które
 * wygasają w ciągu `loyalty.reminder_days`, i najbliższa data. Porcje
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
            ->where('expires_at', '<=', now()->addDays((int) config('loyalty.reminder_days')))
            ->get();

        $sent = 0;

        foreach ($lots->groupBy(fn (LoyaltyEntry $lot): string => $lot->shop_id.'|'.$lot->email) as $group) {
            $sent += $this->remind($group) ? 1 : 0;
        }

        return $sent;
    }

    /**
     * @param  Collection<int, LoyaltyEntry>  $lots  porcje jednego klienta w jednym sklepie
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

        EmailMessage::create([
            'priority' => MailPriority::Mid,
            'shop_id' => $shop->id,
            'from_name' => $shop->name,
            'reply_to' => $shop->contact_email,
            'to_email' => $email,
            'to_name' => trim(($account?->name ?? $name).' '.($account?->surname ?? '')),
            'subject' => 'Twoje punkty wkrótce wygasną — '.$shop->name,
            'preheader' => $points.' pkt wygaśnie '.$date.'.',
            'heading' => 'Punkty wkrótce wygasną',
            'greeting' => Vocative::greeting($name),
            'intro_lines' => array_values(array_filter([
                'W sklepie **'.$shop->name.'** masz punkty, którym kończy się ważność: **'.$points.' pkt** wygaśnie **'.$date.'**.',
                $activated
                    ? 'Możesz je wykorzystać przy kolejnym zamówieniu — w koszyku kliknij „Wykorzystaj".'
                    : 'Punkty są zapisane na ten adres e-mail. Żeby z nich skorzystać, załóż konto w sklepie na ten sam adres — punkty od razu się na nim pojawią.',
            ])),
            'action_text' => $activated ? 'Przejdź do sklepu' : 'Załóż konto',
            'action_url' => $activated ? $host : $host.'/rejestracja',
            'outro_lines' => $activated
                ? ['Saldo i historię punktów znajdziesz w Moim koncie, w zakładce „Punkty".']
                : [],
        ]);
    }
}
