<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = (string) config('auth.defaults.guard', 'web');

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, $guardName);
        }
    }
}
