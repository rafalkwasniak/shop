<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pojedyncze przejście statusu zamówienia (oś czasu). Niezmienne — stąd tylko
 * `created_at` (UPDATED_AT wyłączony). `from_status` bywa null dla zdarzeń bez
 * poprzednika. Tworzone wyłącznie przez Order::changeStatus().
 */
#[Fillable(['from_status', 'to_status', 'note'])]
class OrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Kto dokonał zmiany. `null` znaczy „nie człowiek" i jest normalnym stanem,
     * nie brakiem danych: potwierdzenie płatności przychodzi webhookiem, część
     * przejść robi cron.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Podpis pod wpisem osi czasu. Jedno miejsce dla wszystkich trzech widoków
     * (panel sprzedawcy, panel admina, konto klienta), żeby nie rozjechały się
     * w nazywaniu tego samego zdarzenia.
     */
    public function authorLabel(): string
    {
        $author = $this->author;

        if ($author === null) {
            return 'Automatycznie';
        }

        return trim($author->name.' '.$author->surname);
    }
}
