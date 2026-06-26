<?php

namespace App\Http\Requests\Consent;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Akceptacja zaległych dokumentów na stronie ponownej zgody. Walidacja jest
 * dynamiczna: każdy aktualnie wymagany, jeszcze niezaakceptowany dokument
 * musi mieć zaznaczony checkbox `documents[{id}]`.
 */
class ConsentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();

        return $user->outstandingConsents()
            ->mapWithKeys(fn ($document) => ["documents.{$document->getKey()}" => ['accepted']])
            ->all();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->setCustomMessages(
            collect($this->rules())
                ->keys()
                ->mapWithKeys(fn (string $key) => ["{$key}.accepted" => 'Musisz zaakceptować wszystkie dokumenty, aby kontynuować.'])
                ->all()
        );
    }
}
