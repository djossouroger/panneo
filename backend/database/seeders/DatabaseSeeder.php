<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CategorySeeder::class);

        if (app()->environment('production')) {
            foreach (['ADMIN_EMAIL', 'ADMIN_PASSWORD'] as $variable) {
                if (blank(env($variable))) {
                    throw new \RuntimeException(
                        "Variable d'environnement $variable requise en production (voir docs/RAILWAY_VARIABLES.md)."
                    );
                }
            }
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@panneo.test')],
            [
                'name' => env('ADMIN_NAME', 'Pannéo Admin'),
                'phone' => '+33600000000',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Password123!')),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
