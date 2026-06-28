<?php

namespace Database\Seeders;

use App\Models\Dig;
use Illuminate\Database\Seeder;

class DigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Dig::factory()->count(10)->withLayers(4)->create();
    }
}
