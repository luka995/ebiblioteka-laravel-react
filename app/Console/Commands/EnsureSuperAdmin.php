<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class EnsureSuperAdmin extends Command
{
    protected $signature = 'app:ensure-super-admin
        {--email=admin@ebiblioteka.rs : Email superadmin naloga}
        {--password= : Lozinka superadmin naloga (fallback: env ADMIN_PASSWORD)}';

    protected $description = 'Kreira (ili promovise) superadmin nalog za razvoj i pocetni pristup dashboardu';

    public function handle(): int
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
                'role' => UserRole::SuperAdmin,
            ]);

            $this->info("Superadmin nalog kreiran: {$email}");

            return self::SUCCESS;
        }

        if (! $user->role->isSuperAdmin()) {
            $user->update(['role' => UserRole::SuperAdmin]);
            $this->info("Korisnik {$email} promovisan u superadmin ulogu.");
        } else {
            $this->info("Superadmin nalog vec postoji: {$email}");
        }

        return self::SUCCESS;
    }
}
