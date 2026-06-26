<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\LegalDocumentType;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'surname', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
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
