<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\GlobalSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class EnsureSuperAdmin extends Command
{
    protected $signature = 'app:ensure-super-admin
        {--email=admin@ebiblioteka.rs : Email superadmin naloga}
        {--password= : Lozinka superadmin naloga (fallback: env ADMIN_PASSWORD)}';

    protected $description = 'Kreira (ili promovise) superadmin nalog i upisuje ga u globalna podesavanja';

    public function handle(GlobalSettings $settings): int
    {
        $email = strtolower(trim((string) $this->option('email')));
        $password = $this->option('password') ?: env('ADMIN_PASSWORD', 'fw2wwc');

        $user = User::where('email', $email)->first();

        if ($user === null) {
            $user = User::create([
                'name' => 'Super Administrator',
                'first_name' => 'Super',
                'last_name' => 'Administrator',
                'username' => 'admin',
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            $this->info("Superadmin nalog kreiran: {$email}");
        }

        $settings->addSuperAdmin((int) $user->id);

        $this->info("Korisnik {$email} je u listi superadmina.");

        return self::SUCCESS;
    }
}
