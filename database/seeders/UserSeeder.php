<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::factory()->create([
            'first_name' => 'Regular',
            'last_name' => 'User',
            'email' => 'user@gmail.com',
            'password' => '12345678',
        ])->assignRole(UserRole::User->value);

        User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@gmail.com',
            'password' => '12345678',
        ])->assignRole(UserRole::Admin->value);
    }
}
