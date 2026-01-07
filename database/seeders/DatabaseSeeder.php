<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Test user
        User::create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // Admin user
        User::updateOrCreate(
            ['email' => 'admin@localhost.com'],
            [
                'name'      => 'Admin',
                'password'  => Hash::make('password'),
                'is_admin'  => true,
            ]
        );
    }
}
