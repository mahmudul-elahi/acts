<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        $quotes = [
            ['quote' => 'The journey within is the most important journey you will ever take.', 'author' => 'The Dig'],
            ['quote' => 'Your emotions are messengers. Listen to them without judgment.', 'author' => 'Ancient Wisdom', 'status' => false],
            ['quote' => 'In stillness, we find our truth.', 'author' => 'The Dig'],
            ['quote' => 'Every dig brings you closer to your authentic self.', 'author' => 'Ancient Wisdom'],
        ];

        foreach ($quotes as $quote) {
            $user->quotes()->create($quote);
        }
    }
}
