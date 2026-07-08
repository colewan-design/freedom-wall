<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $username = config('services.admin.username');
        $password = config('services.admin.password');

        if (blank($username) || blank($password)) {
            throw new RuntimeException('Set ADMIN_USERNAME and ADMIN_PASSWORD before running the database seeder.');
        }

        User::updateOrCreate(
            ['username' => $username],
            [
                'name' => 'Admin',
                'email' => "{$username}@freedom-wall.local",
                'password' => Hash::make($password),
            ]
        );
    }
}
