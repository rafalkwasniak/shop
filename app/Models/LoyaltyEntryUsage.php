<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ile punktów wydania (`entry_id`) zeszło z danej porcji (`lot_id`). Dzięki
 * temu anulowane zamówienie oddaje punkty do TYCH SAMYCH porcji, z ich
 * własnym terminem ważności, a nie tworzy świeżej porcji na nowy rok.
 */
#[Fillable(['entry_id', 'lot_id', 'points'])]
class LoyaltyEntryUsage extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<LoyaltyEntry, $this>
     */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(LoyaltyEntry::class, 'lot_id');
    }
}
