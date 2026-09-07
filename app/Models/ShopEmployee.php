<?php

namespace App\Models;

use App\Enums\PanelSection;
use Database\Factories\ShopEmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zatrudnienie użytkownika w sklepie: gdzie pracuje i do czego ma dostęp.
 *
 * Cykl życia wiersza: zaproszenie (`invited_at`) → przyjęcie, czyli ustawienie
 * hasła przez pracownika (`accepted_at`) → ewentualne odebranie dostępu
 * (`revoked_at`). Wiersz nie znika po odebraniu — patrz komentarz w migracji.
 */
#[Fillable(['permissions', 'invited_by', 'invited_at', 'accepted_at', 'revoked_at'])]
class ShopEmployee extends Model
{
    /** @use HasFactory<ShopEmployeeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Czy to członkostwo daje dziś dostęp do sklepu.
     *
     * Zaproszenie, którego nikt nie przyjął, dostępu NIE daje — konto istnieje
     * od chwili wysłania maila (bo musi mieć na czym wisieć link aktywacyjny),
     * ale hasła do niego nie zna jeszcze nikt.
     */
    public function isActive(): bool
    {
        return $this->accepted_at !== null && $this->revoked_at === null;
    }

    /**
     * Czy to członkostwo REALNIE otwiera dziś panel.
     *
     * `isActive()` mówi o samym zatrudnieniu (przyjęte i nieodebrane), a to
     * pytanie dokłada drugi warunek: czy pakiet sklepu nadal daje konta
     * pracownicze. Sklep, który zszedł z pakietu albo nie odnowił abonamentu,
     * nie może zachować działających kont — inaczej wystarczyłoby opłacić
     * Pawilon raz, żeby mieć zespół na zawsze.
     *
     * Dostęp jest WYGASZANY, nie kasowany: wiersz zostaje nietknięty, więc po
     * odnowieniu pakietu wszyscy wracają sami, bez zapraszania od nowa i bez
     * ponownego ustawiania haseł.
     */
    public function isEffective(): bool
    {
        return $this->isActive() && (bool) $this->shop?->allowsEmployees();
    }

    /**
     * Warunek SQL „to członkostwo jest czynne", w kształcie do wstawienia w
     * `whereHas()`. Istnieje po to, żeby definicja czynnego zatrudnienia miała
     * JEDNO miejsce wspólne z `isActive()` — rozjazd oznaczałby, że lista
     * pracowników pokazuje kogo innego, niż wpuszcza brama panelu.
     *
     * @param  Builder<ShopEmployee>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNotNull('accepted_at')->whereNull('revoked_at');
    }

    /**
     * Czy pracownik ma dostęp do danego działu panelu.
     *
     * Odebrane albo nieprzyjęte członkostwo nie daje NICZEGO, niezależnie od
     * tego, co zostało w `permissions`. Pytanie o dział musi być jednym
     * pytaniem — gdyby wołający musiał pamiętać o osobnym sprawdzeniu
     * `isActive()`, prędzej czy później ktoś by zapomniał.
     */
    public function allows(PanelSection $section): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return in_array($section->value, $this->permissions ?? [], true);
    }

    /**
     * Działy, do których ten pracownik ma dostęp — do listy w panelu.
     *
     * Klucze spoza enuma są odrzucane. Wpis w `permissions` przeżywa usunięcie
     * działu z kodu, a `PanelSection::from()` na takim śmieciu rzuciłby
     * wyjątkiem przy renderowaniu listy.
     *
     * @return list<PanelSection>
     */
    public function sections(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $key): ?PanelSection => PanelSection::tryFrom($key),
            $this->permissions ?? [],
        )));
    }
}
