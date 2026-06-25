<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminUser extends Command
{
    protected $signature = 'shop:create-admin
        {--name= : Imię}
        {--surname= : Nazwisko}
        {--email= : Adres e-mail}
        {--password= : Hasło}';

    protected $description = 'Tworzy konto administratora platformy.';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?: text('Imię', required: true),
            'surname' => $this->option('surname') ?: text('Nazwisko', required: true),
            'email' => $this->option('email') ?: text('Adres e-mail', required: true),
            'password' => $this->option('password') ?: password('Hasło (min. 8 znaków)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'surname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = new User;
        $user->name = $data['name'];
        $user->surname = $data['surname'];
        $user->email = $data['email'];
        $user->password = $data['password']; // hashowane przez cast 'hashed'
        $user->role = UserRole::Admin;       // role nie jest mass-assignable — ustawiamy jawnie
        $user->email_verified_at = now();
        $user->save();

        $this->info("Utworzono administratora: {$user->email}");

        return self::SUCCESS;
    }
}
