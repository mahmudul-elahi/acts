<?php

use App\Models\User;
use App\Notifications\SubscriptionExpiringNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

const EXPIRES_TIMESTAMP = 1775000000;

/**
 * Build a customer.subscription.updated webhook payload and dispatch it as the
 * WebhookReceived event Cashier fires for every incoming webhook.
 *
 * @param  array<string, mixed>  $object
 * @param  array<string, mixed>  $previous
 */
function fireSubscriptionUpdated(array $object = [], array $previous = ['cancel_at_period_end' => false]): void
{
    event(new WebhookReceived([
        'id' => 'evt_'.fake()->bothify('????????'),
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => array_merge([
                'id' => 'sub_test',
                'customer' => 'cus_test',
                'cancel_at_period_end' => true,
                'current_period_end' => EXPIRES_TIMESTAMP,
                'cancel_at' => EXPIRES_TIMESTAMP,
            ], $object),
            'previous_attributes' => $previous,
        ],
    ]));
}

function userWithStripeCustomer(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->stripe_id = 'cus_test';
    $user->save();

    return $user;
}

test('scheduling a cancellation notifies the member of the expiry date', function () {
    Notification::fake();
    $user = userWithStripeCustomer();

    fireSubscriptionUpdated();

    Notification::assertSentTo(
        $user,
        SubscriptionExpiringNotification::class,
        fn (SubscriptionExpiringNotification $notification, array $channels) => $channels === ['database', 'broadcast']
            && $notification->expiresAt->timestamp === EXPIRES_TIMESTAMP,
    );
});

test('an unrelated subscription update does not notify', function () {
    Notification::fake();
    userWithStripeCustomer();

    // cancel_at_period_end did not change in this update (only e.g. the price did).
    fireSubscriptionUpdated(previous: ['items' => []]);

    Notification::assertNothingSent();
});

test('an update that was already cancel-at-period-end does not re-notify', function () {
    Notification::fake();
    userWithStripeCustomer();

    fireSubscriptionUpdated(previous: ['cancel_at_period_end' => true]);

    Notification::assertNothingSent();
});

test('expiry alerts respect the subscription alert setting', function () {
    Notification::fake();
    $user = userWithStripeCustomer();
    $user->notificationSettings()->update(['subscription_alerts' => false]);

    fireSubscriptionUpdated();

    Notification::assertNothingSentTo($user);
});

test('a webhook for an unknown customer notifies no one', function () {
    Notification::fake();
    userWithStripeCustomer();

    fireSubscriptionUpdated(object: ['customer' => 'cus_unknown']);

    Notification::assertNothingSent();
});

test('a redelivered cancellation webhook notifies only once', function () {
    $user = userWithStripeCustomer();

    fireSubscriptionUpdated();
    fireSubscriptionUpdated();

    expect($user->notifications()->where('type', 'subscription_expiring')->count())->toBe(1);
});

test('the expiry alert surfaces in the notification feed with a human date', function () {
    $user = userWithStripeCustomer();

    fireSubscriptionUpdated();

    Sanctum::actingAs($user);

    $this->getJson('/api/notifications')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'subscription_expiring')
        ->assertJsonPath(
            'data.0.data.message',
            'Your subscription will expire on '.Carbon::createFromTimestamp(EXPIRES_TIMESTAMP)->format('j F, Y').'.',
        );
});
