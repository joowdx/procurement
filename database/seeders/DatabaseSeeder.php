<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (User::where('email', 'admin@example.com')->doesntExist()) {
            User::factory()
            ->withoutTwoFactor()
            ->administratorAccount()
            ->create();
        }

    }
}
