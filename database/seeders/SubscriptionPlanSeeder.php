<?php

namespace Database\Seeders;

use App\Enums\BillingPeriod;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds the catalog locally only. Stripe products and prices are created
     * through the admin endpoints, not the seeder.
     */
    public function run(): void
    {
        $plans = [
            [
                'badge_name' => 'Popular',
                'title' => 'Monthly',
                'sub_title' => 'For those ready to go deeper',
                'price' => 8.88,
                'billing_period' => BillingPeriod::Monthly,
                'features' => [
                    'Unlimited text, photo & audio journaling',
                    'Unlimited Digs',
                    'Full community participation (post & reply)',
                    'Ad-free experience',
                ],
            ],
            [
                'badge_name' => 'On Sale',
                'title' => 'Annual',
                'sub_title' => 'For those ready to go deeper',
                'price' => 53.33,
                'billing_period' => BillingPeriod::Yearly,
                'features' => [
                    'Everything in Monthly',
                    'Exclusive original meditations',
                    'Advanced progress analytics & export',
                ],
            ],
            [
                'badge_name' => 'Special',
                'title' => 'Lifetime',
                'sub_title' => 'The Lifetime Access',
                'price' => 111.11,
                'billing_period' => BillingPeriod::OnePayment,
                'features' => [
                    'Everything in Premium, forever',
                    'All future premium content & features',
                    'Lifetime community member status',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['title' => $plan['title']],
                ['currency' => 'usd', 'status' => true, ...$plan],
            );
        }
    }
}
