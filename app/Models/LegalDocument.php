<?php

namespace App\Models;

use App\Enums\LegalDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'version', 'content', 'published_at'])]
class LegalDocument extends Model
{
    protected function casts(): array
    {
        return [
            'type' => LegalDocumentType::class,
            'version' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Najnowsza opublikowana wersja danego typu (lub null, gdy brak).
     */
    public static function current(LegalDocumentType $type): ?self
    {
        return static::query()
            ->published()
            ->where('type', $type)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @param  Builder<LegalDocument>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->whereNotNull('published_at');
    }

    /**
     * @return HasMany<UserConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }
}
