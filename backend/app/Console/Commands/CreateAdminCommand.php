<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {--email= : Adresse e-mail de l\'administrateur (défaut : ADMIN_EMAIL) }
        {--password= : Mot de passe de l\'administrateur (défaut : ADMIN_PASSWORD) }
        {--name= : Nom de l\'administrateur (défaut : ADMIN_NAME) }';

    protected $description = 'Crée ou met à jour le compte administrateur (utilisé en production pour l\'amorçage).';

    public function handle(): int
    {
        $email = $this->option('email') ?: env('ADMIN_EMAIL');
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');
        $name = $this->option('name') ?: env('ADMIN_NAME', 'Pannéo Admin');

        if (blank($email) || blank($password)) {
            $this->error('E-mail et mot de passe obligatoires (options --email / --password ou variables ADMIN_EMAIL / ADMIN_PASSWORD).');

            return self::FAILURE;
        }

        if (strlen((string) $password) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');

            return self::FAILURE;
        }

        $admin = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->info("Administrateur prêt : {$admin->email} (id {$admin->id}).");

        return self::SUCCESS;
    }
}
