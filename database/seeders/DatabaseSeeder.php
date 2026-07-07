<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $username = config('services.admin.username');

        User::updateOrCreate(
            ['username' => $username],
            [
                'name' => 'Admin',
                'email' => "{$username}@freedom-wall.local",
                'password' => config('services.admin.password_hash'),
            ]
        );
    }
}
