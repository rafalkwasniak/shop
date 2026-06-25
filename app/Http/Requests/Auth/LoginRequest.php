<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
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
     * Próba uwierzytelnienia na podstawie danych z żądania.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), (int) config('security.login.decay_seconds'));

            throw ValidationException::withMessages([
                'email' => 'Nieprawidłowy adres e-mail lub hasło.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Blokuje logowanie po zbyt wielu nieudanych próbach.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), (int) config('security.login.max_attempts'))) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $message = $seconds >= 60
            ? 'Zbyt wiele prób logowania. Spróbuj ponownie za '.ceil($seconds / 60).' min.'
            : "Zbyt wiele prób logowania. Spróbuj ponownie za {$seconds} s.";

        throw ValidationException::withMessages([
            'email' => $message,
        ]);
    }

    /**
     * Klucz throttlingu: e-mail + IP.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
