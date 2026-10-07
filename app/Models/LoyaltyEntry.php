<?php

namespace App\Models;

use App\Enums\LoyaltyEntryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Wpis w księdze punktów za zakupy: linia historii (`points` ze znakiem), a gdy
 * dodatni — także porcja do wydania (`remaining`). Logika salda, wydawania i
 * wygasania mieszka w `LoyaltyLedger`; model jest tylko nośnikiem danych.
 */
#[Fillable([
    'shop_id', 'email', 'order_id', 'type', 'points', 'remaining', 'point_value',
    'base_amount', 'available_at', 'expires_at', 'note',
])]
class LoyaltyEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LoyaltyEntryType::class,
            'points' => 'integer',
            'remaining' => 'integer',
            'point_value' => 'decimal:2',
            'base_amount' => 'decimal:2',
            'available_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Shop, $this>
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Porcje, z których zeszły punkty tego wydania.
     *
     * @return HasMany<LoyaltyEntryUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(LoyaltyEntryUsage::class, 'entry_id');
    }
}
