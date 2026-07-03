<?php

namespace Database\Seeders;

use App\Models\Ad;
use App\Models\AdImpression;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $ads = Ad::factory()->count(40)->create();
        Ad::factory()->count(10)->paused()->create();

        $users = User::inRandomOrder()->limit(5)->get();

        $ads->each(function (Ad $ad) use ($users): void {
            $users->each(function (User $user) use ($ad): void {
                AdImpression::factory()->create([
                    'ad_id' => $ad->id,
                    'user_id' => $user->id,
                ]);
            });
        });
    }
}
