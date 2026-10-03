<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\ThrottlesLogins;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    use ThrottlesLogins;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'adres e-mail',
            'password' => 'hasło',
        ];
    }

    /**
     * Sprawdza e-mail i hasło, ale NIE loguje — przy 2FA wejście następuje
     * dopiero po kodzie z maila (AuthController, LoginCodeController).
     */
    public function authenticatedUser(): User
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            $this->hitRateLimiter();

            throw ValidationException::withMessages([
                'email' => 'Nieprawidłowy adres e-mail lub hasło.',
            ]);
        }

        $this->clearRateLimiter();

        /** @var User */
        return Auth::getLastAttempted();
    }

    /**
     * Klucz throttlingu: e-mail + IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
