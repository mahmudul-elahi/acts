<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

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
