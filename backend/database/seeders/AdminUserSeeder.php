<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class AdminUserSeeder extends Seeder
{
    /**
     * Idempotently provisions the bootstrap admin from env credentials.
     *
     * On a fresh database (T018 / migrate:fresh --seed) this creates the
     * admin row Filament will use to sign in. On reseed it just refreshes
     * the password — no duplicate users, no email-collision errors.
     */
    public function run(): void
    {
        $email = (string) config('photogallery.admin.email', env('ADMIN_EMAIL', 'admin@example.com'));
        $password = (string) env('ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'is_admin' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
