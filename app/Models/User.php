<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\ConsentChannel;
use App\Enums\LegalDocumentType;
use App\Enums\UserRole;
use Closure;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'surname', 'email', 'phone', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSeller(): bool
    {
        return $this->role === UserRole::Seller;
    }

    /**
     * @return HasMany<UserConsent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    /**
     * Zgody marketingowe sprzedawcy (informacje handlowe od Kramio). Osobne od
     * `consents()`, gdzie żyją akceptacje regulaminu i polityki — tamte są
     * obowiązkowe i nieodwoływalne, te dobrowolne i kanałowe.
     *
     * @return HasMany<UserMarketingConsent, $this>
     */
    public function marketingConsents(): HasMany
    {
        return $this->hasMany(UserMarketingConsent::class);
    }

    /**
     * Czy sprzedawca zgodził się na informacje handlowe w tym kanale (i nie
     * wycofał zgody). TO jest jedyne pytanie, które wolno zadać przed wysłaniem
     * mu oferty — sama rejestracja w Kramio zgodą NIE jest (art. 10 uśude).
     *
     * Maili niezbędnych do umowy (faktura, wygaśnięcie pakietu, awaria) ta zgoda
     * nie dotyczy i nie wolno nią ich blokować.
     */
    public function hasMarketingConsent(ConsentChannel $channel = ConsentChannel::Email): bool
    {
        return (bool) $this->marketingConsents
            ->firstWhere('channel', $channel)
            ?->isActive();
    }

    /**
     * Warunek SQL „zgoda w tym kanale jest czynna", w kształcie do wstawienia w
     * `whereHas()` / `whereDoesntHave()`.
     *
     * Istnieje po to, żeby definicja czynnej zgody miała JEDNO miejsce wspólne z
     * `isActive()` na modelu zgody. Filtr listy sprzedawców i przyszłe narzędzie
     * wysyłki muszą pytać dokładnie tak samo — rozjazd oznaczałby, że admin widzi
     * inny zbiór adresów, niż dostanie wiadomość.
     *
     * @return Closure(Builder<UserMarketingConsent>): void
     */
    public static function activeMarketingConsent(ConsentChannel $channel = ConsentChannel::Email): Closure
    {
        return function ($query) use ($channel): void {
            $query->where('channel', $channel)
                ->whereNotNull('granted_at')
                ->whereNull('revoked_at');
        };
    }

    /**
     * Włącza albo wycofuje zgodę marketingową. Idempotentne — jeden wiersz na
     * parę (użytkownik, kanał).
     *
     * Przy udzielaniu zapisujemy DOWÓD: kiedy, z jakiego IP i na jaką wersję
     * treści (RODO art. 7 każe wykazać, na co dokładnie ktoś się zgodził).
     * Przy wycofaniu zostawiamy wiersz z `revoked_at`, żeby odróżnić „wypisał
     * się" od „nigdy się nie zgodził".
     */
    public function setMarketingConsent(ConsentChannel $channel, bool $granted, ?string $ip = null): void
    {
        $this->marketingConsents()->updateOrCreate(
            ['channel' => $channel],
            $granted
                ? [
                    'granted_at' => now(),
                    'revoked_at' => null,
                    'version' => config('legal.seller_marketing_consent.version'),
                    'ip_address' => $ip,
                ]
                : ['revoked_at' => now()],
        );

        $this->unsetRelation('marketingConsents');
    }

    /**
     * Sklep sprzedawcy (jeden właściciel = jeden sklep). Powstaje przy
     * rejestracji z zarezerwowaną subdomeną.
     *
     * @return HasOne<Shop, $this>
     */
    public function shop(): HasOne
    {
        return $this->hasOne(Shop::class, 'owner_id');
    }

    /**
     * Sklep, którym ten użytkownik ZARZĄDZA w panelu — jedyne miejsce, przez
     * które panel sprzedawcy ma pytać „czyj to sklep".
     *
     * Dziś odpowiedź jest ta sama co `shop()`, więc metoda wygląda na zbędną
     * owijkę. Nie jest: `shop()` odpowiada na pytanie o WŁASNOŚĆ (kto jest
     * `owner_id`), a ta metoda na pytanie o MIEJSCE PRACY. Rozjadą się przy
     * pracownikach sklepu, gdzie zalogowany użytkownik pracuje w cudzym sklepie,
     * nie mając do niego żadnych praw właścicielskich.
     *
     * Dlatego rozróżnienie powstaje ZAWCZASU i osobno: podmiana kilkudziesięciu
     * wywołań w panelu to zmiana mechaniczna i bezpieczna, dopóki obie
     * odpowiedzi są identyczne. Wykonana razem z wprowadzeniem pracowników
     * byłaby nie do odróżnienia od zmian, które faktycznie zmieniają zachowanie.
     *
     * Miejsca pytające o WŁASNOŚĆ zostają przy `shop()` i mają tak zostać:
     * zakładanie sklepu przy rejestracji, lista sprzedawców w panelu admina,
     * rozliczenia pakietu. Pracownik nie jest tam odpowiedzią na żadne pytanie.
     */
    public function currentShop(): ?Shop
    {
        return $this->shop;
    }

    /**
     * Czy konto przeszło aktywację, czyli czy sprzedawca ustawił własne hasło.
     *
     * UWAGA na pułapkę: rejestracja NIE zostawia pustego hasła — wstawia losowy
     * ciąg zastępczy (`Str::password(32)`), bo kolumna jest NOT NULL. Sprawdzanie
     * `password === null` dawałoby więc zawsze fałszywy wynik. Jedynym wiarygodnym
     * znacznikiem jest potwierdzenie adresu, ustawiane dopiero przy aktywacji.
     *
     * Rozróżnienie jest potrzebne przy odzyskiwaniu hasła: komuś, kto nigdy go
     * nie ustawił, wysyłamy link AKTYWACYJNY, a nie „ustaw nowe".
     * Odpowiednik `Customer::isActivated()` po stronie klientów sklepu.
     */
    public function isActivated(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Czy użytkownik zaakceptował AKTUALNĄ wersję danego dokumentu.
     */
    public function hasConsentedToCurrent(LegalDocumentType $type): bool
    {
        $current = LegalDocument::current($type);

        if ($current === null) {
            return true; // brak wymaganego dokumentu = nie ma czego akceptować
        }

        return $this->consents()
            ->where('legal_document_id', $current->getKey())
            ->exists();
    }

    /**
     * Aktualne wymagane dokumenty, których użytkownik jeszcze nie zaakceptował.
     * Sterownik wymaganych typów: config('legal.required_types').
     *
     * @return Collection<int, LegalDocument>
     */
    public function outstandingConsents(): Collection
    {
        $acceptedIds = $this->consents()->pluck('legal_document_id')->all();

        return collect(config('legal.required_types'))
            ->map(fn (LegalDocumentType $type) => LegalDocument::current($type))
            ->filter()
            ->reject(fn (LegalDocument $document) => in_array($document->getKey(), $acceptedIds, true))
            ->values();
    }
}
