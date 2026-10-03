<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LoginChallenges;
use Illuminate\Console\Command;

/**
 * Wyjście awaryjne z logowania dwuetapowego, gdy mail nie dochodzi (padł SMTP,
 * skrzynka odrzuca). Kolejność: na stronie podać e-mail i hasło, potem tutaj
 * wygenerować kod i wpisać go na ekranie kodu. Hasło nadal jest wymagane —
 * komenda zastępuje tylko maila.
 */
class IssueLoginCode extends Command
{
    protected $signature = 'auth:login-code {email : Adres konta, które właśnie się loguje}';

    protected $description = 'Wystawia kod logowania na ekran zamiast do maila (awaria poczty).';

    public function handle(LoginChallenges $challenges): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (! $user) {
            $this->error('Nie ma konta z tym adresem.');

            return self::FAILURE;
        }

        $code = $challenges->issueForConsole($user);

        if ($code === null) {
            $this->error('To konto nie czeka na kod. Najpierw podaj e-mail i hasło na stronie logowania.');

            return self::FAILURE;
        }

        $minutes = (int) config('security.two_factor.ttl_minutes');
        $this->info("Kod logowania: {$code} (ważny {$minutes} min, poprzedni przestał działać).");

        return self::SUCCESS;
    }
}
